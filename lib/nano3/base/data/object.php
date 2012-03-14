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

abstract class Container extends Object
                         implements \Iterator, \ArrayAccess, \Countable
{
  protected $data_itemclass;           // A non-abstract subclass of Data\Item.
  protected $data_index = array();     // Index for hash access.

  // Clear our data, including index.
  public function clear ($opts=array())
  {
    $this->data = array();
    $this->data_index = array();
  }

  // Add an item to our index.
  abstract public function add_data_index ($item);
  // Get the index number based on key.
  abstract public function get_data_index ($key);

  public function append ($item)
  {
    $this->data[] = $item;
    $this->add_data_index($item);
  }

  public function insert ($item, $pos=0)
  {
    if ($pos)
    {
      if (!is_array($item))
      { // Wrap singletons into an array.
        $item = array($item);
      }
      array_splice($this->data, $pos, 0, $item);
    }
    else
    {
      array_unshift($this->data, $item);
    }
    $this->add_data_index($item);
  }

  public function swap ($pos1, $pos2)
  {
    $new1 = $this->data[$pos2];
    $this->data[$pos2] = $this->data[$pos1];
    $this->data[$pos1] = $new1;
  }

  // Iterator interface, uses $this->data as its source.
  public function current ()
  {
    return current($this->data);
  }

  public function key ()
  {
    return key($this->data);
  }

  public function next ()
  {
    return next($this->data);
  }

  public function rewind ()
  {
    return reset($this->data);
  }

  public function valid ()
  {
    return ($this->current() !== False);
  }

  // ArrayAccess Interface. Uses $this->data_index for its source.
  public function offsetExists ($offset)
  {
    return array_key_exists($offset, $this->data_index);
  }

  public function offfsetGet ($offset)
  {
    if (isset($this->data_index[$offset]))
      return $this->data_index[$offset];
  }

  public function offsetSet ($offset, $value)
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      $this->data[$index] = $value;
    }
    $this->data_index[$offset] = $value;
  }

  public function offsetUnset ($offset)
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      array_splice($this->data, $index, 1);
    }
    unset($this->data_index[$offset]);
  }

  // Countable interface.
  public function count ()
  {
    return count($this->data);
  }

  // Finally, the is() method is separate from offsetExists.
  public function is ($key)
  {
    return isset($this->data_index[$key]);
  }

  // TODO: Add find() and match() functions.

}

// TODO: Add the Item class.


