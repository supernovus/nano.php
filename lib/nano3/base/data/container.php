<?php
/**
 * Data Container
 */

namespace Nano3\Base\Data;

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

  // TODO: Basic versions of add_data_index() and get_data_index().

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


