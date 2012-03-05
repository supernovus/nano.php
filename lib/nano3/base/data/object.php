<?php

/* Data\Object -- Base class for all Nano3\Base\Data classes.
 *
 * Based on my old DataObjects class from PHP-Common, but far more
 * generalized. This version knows nothing about the data formats.
 *
 * The load() method, which can be used in the constructor, will determine
 * the data type either by a 'type' parameter passed to it, or by calling
 * a detect_data_type() method if it exists. If will then look for a
 * method called load_$type() which will be used to load the data.
 *
 * It is expected that custom methods to perform operations on the data
 * will be added, as well as operations to return the data in specific
 * formats (typically the same ones that you accept in the load() statement.)
 *
 */

namespace Nano3\Base\Data;

abstract class Object
{
  protected $data;             // The actual data we represent.
  protected $parent;           // Will be set if we have a parent object.

  public function __construct ($mixed=Null, $opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (method_exists($this, 'data_init'))
    { // The data_init can set up pre-requisites to loading our data.
      // It CANNOT reference our data, as that has not been loaded yet.
      $this->data_init();
    }

    // How we proceed depends on if we have initial data.
    if (isset($mixed))
    { // Load the passed data.
      $loadopts = array('clear'=>False, 'prep'=>True, 'post'=>True);
      if (isset($opts['type']))
      {
        $loadopts['type'] = $opts['type'];
      }
      $this->load($mixed, $loadopts);
    }
  }

  public function load ($data, $opts=array())
  { // If we set the 'clear' option, clear our any existing data.
    if (isset($opts['clear']) && $opts['clear'])
    {
      $this->clear();
    }
    // If we set the 'prep' option, send the data to data_prep()
    // for initial preparations which will return the prepared data.
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
    elseif (method_exists($this, 'detect_data_type'))
    {
      $type = $this->detect_data_type($data);
    }
    else
    {
      throw new Exception("Could not determine data type.");
    }
    // Handle the data type.
    if (isset($type))
    {
      $method = "load_$type";
      if (method_exists($this, $method))
      {
        // If this method returns False, something went wrong.
        // If it returns an array or object, that becomes our data.
        // If it returns Null or True, we assume the method set the data.
        $return = $this->$method($data, $opts);
        if ($return === False)
        {
          throw new Exception("Could not load data.");
        }
        elseif (is_array($return) || is_object($return))
        {
          $this->data = $return;
        }
      }
      else
      {
        throw new Exception("Could not handle data type.");
      }
    }
    else
    {
      throw new Exception("Unsupported data type.");
    }
    // If we have set the 'post' option, call data_post().
    if (isset($opts['post']) && $opts['post'] 
      && method_exists($this, 'data_post'))
    {
      $this->data_post();
    }
  }

  // Default version of clear(). Override as needed.
  public function clear ($opts=array())
  {
    $this->data = Null;
  }

  // Spawn a new empty data object.
  public function spawn ($opts=array())
  {
    return new $this (Null, $opts);
  }

}

