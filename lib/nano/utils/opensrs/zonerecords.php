<?php

namespace Nano\Utils\OpenSRS;

/**
 * A class representing a set of DNS records.
 */
class ZoneRecords implements \JsonSerializable
{
  /**
   * Constant mapping the record types to corresponding PHP classes.
   */
  const classes =
  [
    'A'     => __NAMESPACE__.'\ARecord',
    'AAAA'  => __NAMESPACE__.'\AAAARecord',
    'CNAME' => __NAMESPACE__.'\CNAMERecord',
    'MX'    => __NAMESPACE__.'\MXRecord',
    'SRV'   => __NAMESPACE__.'\SRVRecord',
    'TXT'   => __NAMESPACE__.'\TXTRecord',
  ];

  /**
   * All of our records for each type.
   */
  protected $records =
  [
    'A'     => [],
    'AAAA'  => [],
    'CNAME' => [],
    'MX'    => [],
    'SRV'   => [],
    'TXT'   => [],
  ];

  /**
   * Build a ZoneRecord object.
   *
   * @param array $records  A PHP array of records.
   *
   * The array is assumed to be in the format returned by the
   * SRS_RES_XML's getContents(), srsAttributes(), or ::srsBody()
   * methods. Rather than building this directly use the
   * SRS_RES_XML's dnsRecords() method.
   */
  public function __construct ($records)
  {
    foreach ($records as $type => $recs)
    {
      if (isset($recs) && is_array($recs))
      {
        foreach ($recs as $recdef)
        {
          $classname = $this::classes[$type];
          $record = new $classname($recdef, $this);
          $this->records[$type][] = $record;
        }
      }
    }
  }

  /**
   * Serialize as JSON, using toArray().
   */
  public function jsonSerialize ()
  {
    return $this->toArray();
  }

  /**
   * Convert our records into a PHP array.
   *
   * Basically, restores them the format the constructor used to
   * build this object in the first place.
   */
  public function toArray ()
  {
    $records = [];
    foreach ($this->records as $type => $srecs)
    {
      if (count($srecs) > 0)
      {
        $trecs = [];
        foreach ($srecs as $srec)
        {
          $trec = $srec->toArray();
          if (isset($trec))
            $trecs[] = $trec;
        }
        $records[$type] = $trecs;
      }
    }
    return $records;
  }

  /**
   * Serialize our records back into an SRS_REQ_XML <item/> element.
   *
   * @param SRS_REQ_XML $el  The <item/> element we're serializing to.
   */
  public function toReqXML ($el)
  {
    $records = $this->toArray();
    $el->addAssoc($records);
  }

  /**
   * Find records of a certain type.
   *
   * @param  str  $type        One of the valid record types.
   * @param  str  $subdomain   If passed, only this subdomain.
   * @param  bool $multiple    Used if subdomain has more than one.
   *
   * @return mixed   Either a single record, or an array of records.
   */
  public function find ($type, $subdomain=null, $multiple=false)
  {
    if (!isset($subdomain))
    {
      return $this->records[$type];
    }
    $records = [];
    foreach ($this->records[$type] as $record)
    {
      if ($record->subdomain == $subdomain)
      {
        if ($multiple)
          $records[] = $record;
        else
          return $record;
      }
    }
    return $records;
  }
}

/**
 * Abstract parent class for all zone records.
 */
abstract class ZoneRecord
{
  /**
   * The parent ZoneRecords object.
   */
  protected $parent;
  /**
   * The subdomain (if any) for this record.
   */
  public $subdomain;

  /**
   * Build a new ZoneRecord.
   *
   * @param array $record  The PHP array representing the record.
   * @param ZoneRecords $parent  The parent ZoneRecords object.
   */
  public function __construct ($record, $parent)
  {
    $this->parent = $parent;
    foreach ($record as $key => $val)
    {
      if (property_exists($this, $key))
      {
        $this->$key = $val;
      }
    }
  }

  /**
   * Convert this into a PHP array.
   */
  public function toArray ()
  {
    $array = [];
    $props = get_object_vars($this);
    foreach ($props as $key => $val)
    {
      if ($key == 'parent') continue; // skip parent.
      if (isset($val))
        $array[$key] = $val;
    }
    return $array;
  }

  /**
   * Get the parent ZoneRecords object.
   */
  public function getParent()
  {
    return $this->parent;
  }
}

/**
 * An A Record.
 */
class ARecord extends ZoneRecord
{
  /**
   * The IP address.
   */
  public $ip_address;
}

/**
 * An AAAA Record.
 */
class AAAARecord extends ZoneRecord
{
  /**
   * The IPv6 address.
   */
  public $ipv6_address;
}

/**
 * A CNAME Record.
 */
class CNAMERecord extends ZoneRecord
{
  /**
   * The aliased hostname.
   */
  public $hostname;
}

/**
 * A MX Record.
 */
class MXRecord extends ZoneRecord
{
  /**
   * The MX priority.
   */
  public $priority;
  /**
   * The mail server hostname.
   */
  public $hostname;
}

/**
 * A SRV Record.
 */
class SRVRecord extends ZoneRecord
{
  public $priority;
  public $weight;
  public $hostname;
  public $port;
}

/**
 * A TXT Record.
 */
class TXTRecord extends ZoneRecord
{
  /**
   * The text for the TXT record.
   */
  public $text;
}

