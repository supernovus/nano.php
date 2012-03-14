<?php

/* Data\Object -- Base class for all Nano3\Base\Data classes.
 *
 * Based on my old DataObjects class from PHP-Common, but far more
 * generalized. This version knows nothing about the data formats.
 *
 */

namespace Nano3\Base\Data;

abstract class Object
{
  protected $data   = array(); // The actual data we represent.
  protected $parent;           // Will be set if we have a parent object.
  protected $types;            // Types we know how to convert.
                               // This needs to be overridden.

  public function __construct ($mixed=Null, $opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (method_exists($this, 'data_init'))
    {
      $this->data_init();
    }
    // We only continue if there was initial data sent.
    if (isset($mixed))
    {
      $this->load_data(
        $mixed, array('clear'=>False, 'prep'=>True, 'post'=>True)
      );
    }
  }

  public function load_data ($data, $opts=array())
  { // First, if we set clear, let's clear the data.
    if (isset($opts['clear']) && $opts['clear'])
    {
      $this->clear();
    }
    if (isset($opts['prep']) && $opts['prep'] 
      && method_exists($this, 'data_prep'))
    {
      $data = $this->data_prep($data);
    }
    // Figure out the data type.
    $type = Null;
    if (isset($opts['type']))
    {
      $type = $opts['type'];
    }
    elseif (method_exists($this, 'data_detect_type'))
    {
      $type = $this->data_detect_type($data);
    }
    else
    {
      throw new Exception("Could not determine data type.");
    }
    // Handle the data type.
    if (isset($type) && isset($this->types[$type]))
    {
      $method = $this->types[$type];
      // If this method returns False, something went wrong.
      // If it returns an array, we set the data to that array.
      // If it returns Null or True, we assume the method set the data.
      $return = $this->$method($data);
      if ($return === False)
      {
        throw new Exception("Could not load data.");
      }
      elseif (is_object($return))
      {
        $this->data = $return;
      }
    }
    else
    {
      throw new Exception("Could not handle data type.");
    }
  }

  // Default version of clear(). Override as needed.
  public function clear ($opts=array())
  {
    $this->data = array();
  }

  // Spawn a new empty data object.
  public function spawn ($opts=array())
  {
    return new $this (Null, $opts);
  }

}

