<?php

namespace Nano4\DB\Mongo;

class Results implements \Iterator, \Countable
{
  use \Nano4\Data\JSON;

  public $parent;
  protected $find_query = [];
  protected $find_opts  = [];
  protected $class_opts = [];
  protected $results;

  public function __construct ($opts=[])
  {
    $this->class_opts = $opts;

    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (isset($opts['find']))
    {
      $this->find_query = $opts['find'];
    }
    if (isset($opts['findopts']))
    {
      $this->find_opts = $opts['findopts'];
    }
  }

  public function count ()
  {
    return count($this->results);
  }

  public function rewind ()
  {
    $data = $this->parent->get_collection();
    $results = $data->find($this->find_query, $this->find_opts);
    $results = new \IteratorIterator($results);
    $results->rewind();
    $this->results = $results;
  }

  public function current ()
  {
    return $this->parent->wrapRow($this->results->current(), $this->class_opts);
  }

  public function next ()
  {
    $this->results->next();
  }

  public function key ()
  {
    return $this->results->key();
  }

  public function valid ()
  {
    return $this->results->valid();
  }

  public function to_array ($opts=[])
  {
    $array = [];
    foreach ($this as $that)
    {
      $item = $that->to_array();
      $array[] = $item;
    }
    return $array;
  }

}