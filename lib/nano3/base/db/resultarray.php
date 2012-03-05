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

namespace Nano3\Base\DB;

class ResultArray implements \ArrayAccess, \Countable, \Iterator
{
  protected $parent;
  protected $results;
  protected $primary_key = 'id';

  public function __construct ($sql, $bind, $parent, $pk=Null)
  {
    $this->parent = $parent;
    $query = $this->parent->query($sql);
    $query->execute($bind);
    $this->results = $query->fetchAll();
    if (isset($pk))
      $this->primary_key = $pk;
  }

  // Iterator interface.
  public function rewind ()
  {
    return rewind($this->results);
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
    throw new Exception("You cannot set query results.");
  }
  public function offsetExists ($offset)
  {
    return isset($this->results[$offset]);
  }
  public function offsetUnset ($offset)
  {
    throw new Exception("You cannot unset query results.");
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
