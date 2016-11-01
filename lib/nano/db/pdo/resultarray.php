<?php

/**
 * Treat query results as an array.
 *
 * An alternative to ResultSet that provides array-like access
 * to the results of a query.
 *
 * It uses fetchAll() instead of fetch() internally.
 *
 */

namespace Nano\DB\PDO;

class ResultArray implements \ArrayAccess, \Countable, \Iterator
{
  use \Nano\DB\ResultToArray, \Nano\Data\JSON;

  protected $parent;
  protected $results;
  protected $primary_key = 'id';

  // Compatibility with old constructor.
  public function __construct ($opts, $bind=null, $parent=null, $pk=null)
  {
    if (is_array($opts) && isset($opts['parent']))
      $this->parent = $opts['parent'];
    elseif (isset($parent))
      $this->parent = $parent;
    else
      throw new \Exception("No 'parent' specified in ResultArray constructor.");

    if (is_array($opts) && isset($opts['query']))
    {
      $query = $opts['query'];
      $sopts = $opts;
    }
    elseif (is_string($opts))
    {
      $sopts = [];
      $query =
      [
        'where' => $opts,
        'data'  => $bind,
      ];
    }
    else
    {
      $sopts = [];
      $query = [];
    }

    if (isset($opts['pk']))
      $this->primary_ley = $opts['pk'];
    if (isset($pk))
      $this->primary_key = $pk;

    $sopts['rawRow'] = true;

    $stmt = $this->parent->selectQuery($query, $sopts);
    $this->results = $stmt->fetchAll();
  }

  // Iterator interface.
  public function rewind ()
  {
    return reset($this->results);
  }
  public function current ()
  {
    $row = current($this->results);
    return $this->parent->wrapRow($row);
  }
  public function next ()
  {
    return next($this->results);
  }
  public function key ()
  {
    return key($this->results);
  }
  public function valid ()
  {
    $key = key($this->results);
    return ($key !== Null && $key !== False);
  }

  // Countable interface.
  public function count ()
  {
    return count($this->results);
  }

  // ArrayAccess interface.
  public function offsetGet ($offset)
  {
    return $this->parent->wrapRow($this->results[$offset]);
  }
  public function offsetSet ($offset, $value)
  {
    throw new \Exception("You cannot set query results.");
  }
  public function offsetExists ($offset)
  {
    return isset($this->results[$offset]);
  }
  public function offsetUnset ($offset)
  {
    throw new \Exception("You cannot unset query results.");
  }

  // The same concept as the map function from ResultSet.
  // Except this version is acting on the array of results instead
  // of our own interfaces. Thus it's slightly faster and has no
  // need to initialize an item class.
  public function map ($valattr, $keyattr=Null)
  {
    if (is_null($keyattr))
    {
      $keyattr = $this->primary_key;
    }
    $map = array();
    foreach ($this->results as $item)
    {
      $map[$item[$keyattr]] = $item[$valattr];
    }
    return $map;
  }

}
