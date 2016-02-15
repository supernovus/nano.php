<?php

/**
 * A special base class meant for use with DBModel/DBItem.
 * It represents the results of a query as an iterable item.
 */

namespace Nano4\DB;

class ResultSet implements \Iterator, \Countable
{
  protected $query;       // The SQL query we represent.
  protected $parent;      // The DBModel which called us.

  protected $class_opts;  // Our constructor options.

  protected $results;     // The PDOResult object representing our results.
  protected $current;     // The current item.

  protected $primary_key = 'id'; // The primary key used by our table.

  // Compatibility with the old constructor.
  public function __construct ($opts, $bind=null, $parent=null, $pk=null)
  {
    if (is_array($opts) && isset($opts['parent']))
      $this->parent = $opts['parent'];
    elseif (isset($parent))
      $this->parent = $parent;
    else
      throw new \Exception("No 'parent' specified in ResultSet constructor.");

    if (is_array($opts) && isset($opts['query']))
    {
      $this->class_opts = $opts;
      $this->query = $opts['query'];
    }
    elseif (is_string($opts))
    {
      $this->class_opts = [];
      $this->query =
      [
        'where' => $opts,
        'data'  => $bind,
      ];
    }
    else
    {
      $this->class_opts = [];
      $this->query = [];
    }

    if (isset($opts['pk']))
      $this->primary_key = $opts['pk'];
    elseif (isset($pk))
      $this->primary_key = $primary_key;
  }

  public function rewind ()
  {
    $opts = $this->class_opts;
    $opts['rawDocument'] = true;
    $this->results = $this->parent->selectQuery($this->query, $opts);
    $this->next();
  }

  public function current ()
  {
    return $this->parent->wrapRow($this->current);
  }

  public function next ()
  {
    $this->current = $this->results->fetch();
  }

  public function key ()
  {
    $pk = $this->primary_key;
    return $this->current[$pk];
  }

  public function valid ()
  {
    if ($this->current)
      return true;
    return false;
  }

  // Create an associative array of key => val.
  // It requires a full iteration of our data.
  public function map ($valattr, $keyattr=Null)
  {
    if (is_null($keyattr))
    {
      $keyattr = $this->primary_key;
    }
    $map = array();
    foreach ($this as $item)
    {
      $map[$item[$keyattr]] = $item[$valattr];
    }
    return $map;
  }

  public function count ()
  {
    return $this->parent->rowcount($this->query);
  }

}

