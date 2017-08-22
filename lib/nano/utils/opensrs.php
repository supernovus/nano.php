<?php

/**
 * OpenSRS API Helper library.
 *
 * This is under construction and is currently fairly limited.
 * It currently only supports looking up domain availability,
 * getting the DNS Zones hosted at OpenSRS, and setting the DNS Zones
 * again after making modifications. This is what I'm using it for,
 * so no other features have been put into place yet.
 */

namespace Nano\Utils;

/**
 * Contant for OpenSRS URLs
 */
const OPENSRS_URLS =
[
  'live' => 'https://rr-n1-tor.opensrs.net:55443',
  'test' => 'https://horizon.opensrs.net:55443',
];

// Functions

/**
 * Get an OpenSRS Request XML document.
 *
 * @param str $string  XML string to parse.
 * @return SRS_REQ_XML  Document object.
 */
function get_sreq ($string)
{
  $args = func_get_args();
  if (isset($args[0]) && !isset($args[1]))
  {
    $args[1] = '\Nano\Utils\SRS_REQ_XML';
  }
  return call_user_func_array('simplexml_load_string', $args);
}

/**
 * Get an OpenSRS Response XML document.
 *
 * @param str $string  XML string to parse.
 * @return SRS_REQ_XML  Document object.
 */
function get_sres ($string)
{
  $args = func_get_args();
  if (isset($args[0]) && !isset($args[1]))
  {
    $args[1] = '\Nano\Utils\SRS_RES_XML';
  }
  return call_user_func_array('simplexml_load_string', $args);
}

/**
 * Determine if an array is associative or not.
 *
 * @param array $a  PHP Array to test.
 * @return bool  Is the array associative?
 */
function is_assoc ($a)
{
  if (!is_array($a) || empty($a))
  { // Not an array, or an empty array.
    return null;
  }
  foreach (array_keys($a) as $k => $v)
  {
    if ($k !== $v)
    {
      return true;
    }
  }
  return false;
}

// Classes

/**
 * OpenSRS API client.
 */
class OpenSRS
{
  protected $username;
  protected $apikey;
  protected $url;

  public $debug = false;

  /**
   * Build a new OpenSRS client.
   *
   * @param str $username  The reseller username.
   * @param str $apikey    The reseller API key.
   */
  public function __construct ($username, $apikey)
  {
    $this->username = $username;
    $this->apikey   = $apikey;
    $this->use_test();
  }

  /**
   * Use the testing service.
   */
  public function use_test ()
  {
    $this->url = OPENSRS_URLS['test'];
  }

  /**
   * Use the live service.
   */
  public function use_live ()
  {
    $this->url = OPENSRS_URLS['live'];
  }

  /**
   * Get an SRS_REQ_XML object ready to be populated.
   */
  public function newRequest ()
  {
    $xmlTemplate = <<<EOD
<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
<OPS_envelope>
  <header>
    <version>0.9</version>
  </header>
  <body></body>
</OPS_envelope>
EOD;

    $xml = get_sreq($xmlTemplate);
    return $xml;
  }

  /**
   * Return a SRSCurl instance ready to be used.
   *
   * @param str $xml    [Optional] XML text for signature.
   * @param array $opts [Optional] Options for Curl constructor.
   */
  public function newCurl ($xml=null, $opts=[])
  {
    $curl = new SRSCurl($opts);
    $curl->content_type('text/xml');
    $curl->headers['X-Username'] = $this->username;
    if (isset($xml))
    {
      $curl->setSignature($this->apikey, $xml);
    }
    return $curl;
  }

  /**
   * Given a data structure, build a Request and Curl object,
   * and send the request to the service.
   *
   * @param array $data  A structure representing the request body.
   * @return SRS_RES_XML  The response object.
   */
  public function simpleRequest ($data)
  {
    $request = $this->newRequest();
    $request->body->addDataBlock($data);
    $xml = $request->asXML();
    if ($this->debug)
    {
      error_log("[DEBUG.Request]: $xml");
    }
    $curl = $this->newCurl($xml);
    $response = $curl->post($this->url, $xml, true);
    if (substr($response, 0, 5) !== '<?xml')
      throw new InvalidResponseException();
    return get_sres($response);
  }

  /**
   * Look up domain availability.
   *
   * @param str $domain  The domain to look up.
   * @return SRS_RES_XML  The response object.
   */
  public function lookupDomain ($domain)
  {
    $data = 
    [
      'protocol'   => 'XCP',
      'action'     => 'lookup',
      'object'     => 'domain',
      'attributes' =>
      [
        'domain' => $domain,
      ],
    ];
    return $this->simpleRequest($data);
  }

  /**
   * Get the DNS zones for a given domain.
   *
   * @param str $domain   The domain we are querying.
   * @return SRS_RES_XML  The response object.
   */
  public function getDNSZone ($domain)
  {
    $data =
    [
      'protocol' => 'XCP',
      'action' => 'get_dns_zone',
      'object' => 'domain',
      'attributes' =>
      [
        'domain' => $domain,
      ],
    ];
    return $this->simpleRequest($data);
  }

  /**
   * Set the DNS zones for a given domain.
   *
   * @param str $domain  The domain we are updating.
   * @param ZoneRecords $records  The updated records object.
   * @return SRS_RES_XML  The response object.
   */
  public function setDNSZone ($domain, $records)
  {
    $data =
    [
      'protocol' => 'XCP',
      'action'   => 'set_dns_zone',
      'object'   => 'domain',
      'attributes' =>
      [
        'domain'  => $domain,
        'records' => $records,
      ],
    ];
    return $this->simpleRequest($data);
  }

}

/**
 * OpenSRS Request XML class.
 *
 * Works like a regular SimpleXMLElement, but with some added
 * methods that make working with the OpenSRS API format easier.
 */
class SRS_REQ_XML extends \SimpleXMLElement
{
  /**
   * Add a <data_block/> element.
   *
   * @param array $data  If specified, will be the child content.
   * @return SRS_REQ_XML  The <data_block/> element.
   */
  public function addDataBlock ($data=null)
  {
    $dblock = $this->addChild('data_block');
    if (isset($data) && is_array($data))
    {
      if (is_assoc($data))
      {
        $dblock->addAssoc($data);
      }
      else
      {
        $dblock->addArray($data);
      }
    }
    return $dblock;
  }

  /**
   * Add a <dt_assoc/> element.
   *
   * @param array $data  If specified, will be the child content.
   * @return SRS_REQ_XML  The <dt_assoc/> element.
   */
  public function addAssoc ($data=null)
  {
    $assoc = $this->addChild('dt_assoc');
    if (isset($data) && is_array($data))
    {
      foreach ($data as $key => $val)
      {
        $assoc->addItem($key, $val);
      }
    }
    return $assoc;
  }

  /**
   * Add a <dt_array/> element.
   *
   * @param array $data  If specified, will be the child content.
   * @return SRS_REQ_XML  The <dt_array/> element.
   */
  public function addArray ($data=null)
  {
    $array = $this->addChild('dt_array');
    if (isset($data) && is_array($data))
    {
      foreach ($data as $key => $val)
      {
        $array->addItem($key, $val);
      }
    }
    return $array;
  }

  /**
   * Add an <item key="name" /> element.
   *
   * @param str|int $key  The array key for the item.
   * @param mixed $val  The value for the item.
   *
   * The value may be a string, number, associative array, flat array,
   * or an object with a 'toReqXML()' method to handle serialization.
   *
   * @return SRS_REQ_XML  The <item/> element.
   */
  public function addItem ($key, $val)
  {
    if (is_string($val) || is_numeric($val))
    {
      $iel = $this->addChild('item', $val);
      $iel['key'] = $key;
    }
    elseif (is_array($val))
    {
      $iel = $this->addChild('item');
      $iel['key'] = $key;
      if (is_assoc($val))
      {
        $iel->addAssoc($val);
      }
      else
      {
        $iel->addArray($val);
      }
    }
    elseif (is_object($val) && is_callable([$val, 'toReqXML']))
    {
      $iel = $this->addChild('item');
      $iel['key'] = $key;
      $val->toReqXML($iel);
    }

    if (!isset($iel))
      throw new InvalidItemException();

    return $iel;
  }

}

/**
 * OpenSRS Response XML class.
 *
 * Works like a regular SimpleXMLElement, but with some added
 * methods that make working with the OpenSRS API format easier.
 */
class SRS_RES_XML extends \SimpleXMLElement
{
  /**
   * Get the root element.
   */
  public function rootElement ()
  {
    return simplexml_import_dom(
      dom_import_simplexml($this)->ownerDocument->documentElement,
      get_class($this)
    );
  }

  /**
   * Was the request successful?
   */
  public function isSuccess ()
  {
    $root = $this->rootElement();
    $is_succ = $root->xpath('//item[@key="is_success"]');
    if (isset($is_succ) && count($is_succ) > 0)
    {
      if ((string)$is_succ[0] == '1')
      {
        return true;
      }
    }
    return false;
  }

  /**
   * Return a PHP array representing the 'attributes' property.
   */
  public function srsAttributes ()
  {
    $root = $this->rootElement();
    $attrXML = $root->xpath('//item[@key="attributes"]');
    if (isset($attrXML) && count($attrXML) > 0)
    {
      $attrXML = $attrXML[0]; // there can be only one.
      return $attrXML->getContents();
    }
  }

  /**
   * Return a PHP array representing all returned body properties.
   */
  public function srsBody ()
  {
    $root = $this->rootElement();
    return $root->body->getContents();
  }

  /**
   * For any element, if it has a <dt_assoc/> child, return an 
   * associative array, or if it has a <dt_array/> child, return 
   * a flat array.
   */
  public function getContents ()
  {
    if (isset($this->dt_assoc))
    {
      return $this->dt_assoc->getItems(true);
    }
    elseif (isset($this->dt_array))
    {
      return $this->dt_array->getItems(false);
    }
  }

  /**
   * Used to either a <dt_assoc/> or <dt_array/> element, it will
   * return all <item/> elements a PHP array. It will further
   * convert any nested <dt_assoc/> or <dt_array/> structures.
   */
  public function getItems ($isAssoc)
  {
    if (!isset($this->item))
      return null;
    $items = [];
    foreach ($this->item as $item)
    {
      $key = (string)$item['key'];
      if (isset($item->dt_assoc))
      {
        $val = $item->dt_assoc->getItems(true);
      }
      elseif (isset($item->dt_array))
      {
        $val = $item->dt_array->getItems(false);
      }
      else
      {
        $val = (string)$item;
      }
      if (!$isAssoc)
        $key = intval($key);
      $items[$key] = $val;
    }
    return $items;
  }

  /**
   * Return the DNS records as an object.
   *
   * @return \Nano\Utils\OpenSRS\ZoneRecords  The records object.
   */
  public function dnsRecords ()
  {
    $attrs = $this->srsAttributes();
    if (isset($attrs['records']))
    {
      return new OpenSRS\ZoneRecords($attrs['records']);
    }
  }

}

/**
 * OpenSRS Curl extension class.
 *
 * Works just like \Nano\Utils\Curl, but with helpers
 * for working with the OpenSRS API.
 */
class SRSCurl extends Curl
{
  /**
   * Set the X-Signature header.
   *
   * @param str $apikey  The reseller API key.
   * @param str $xml     The XML text of the request.
   */
  public function setSignature ($apikey, $xml)
  {
    $sig = md5(md5($xml.$apikey).$apikey);
    $this->headers['X-Signature'] = $sig;
  }
}

// Exceptions.

/**
 * Exception for invalid item.
 */
class InvalidItemException extends \Exception
{
  protected $message = 'Invalid item';
}

/**
 * Exception for Invalid response.
 */
class InvalidResponseException extends \Exception
{
  protected $message = 'Invalid response';
}

