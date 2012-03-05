<?php
/**
 * Arrayish Data Structure.
 *
 * Supports iteration, countability and array-like access.
 *
 * It detects arrays and JSON strings (it also detects XML strings,
 * but does not provide a load_xml_string() method so you'll need to do that
 * yourself if you want to be able to load XML strings.)
 *
 */

namespace Nano3\Base\Data;

abstract class Arrayish extends Object
                        implements \Iterator, \ArrayAccess, \Countable
{

  protected $data = array(); // Our default value is an array.

  // Default version of detect_data_type().
  // Feel free to override it, and even call it using parent.
  protected function detect_data_type ($data)
  {
    if (is_array($data))
    {
      return 'array';
    }
    elseif (is_string($data))
    {
      return $this->detect_string_type($data);
    }
  }

  // Detect the type of string being loaded.
  // This is very simplistic, you may want to override it.
  // It currently supports JSON strings starting with { and [
  // and XML strings starting with <. You'll need to implement
  // a load_xml_string() method if you want XML strings to work.
  protected function detect_string_type ($string)
  {
    $fc = substr(trim($string), 0, 1);
    if ($fc == '<')
    { // XML detected.
      return 'xml_string';
    }
    elseif ($fc == '[' || $fc == '{')
    { // JSON detected.
      return 'json';
    }
  }

  // This is very cheap. Override as needed.
  public function load_array ($array, $opts=Null)
  {
    $this->data = $array;
  }

  // Again, pretty cheap, but works well.
  public function load_json ($json, $opts=Null)
  {
    $array = json_decode($json, True);
    return $this->load_array($array, $opts);
  }

  // Output as an array. Just as cheap as load_array().
  public function to_array ($opts=Null)
  {
    return $this->data;
  }

  // Output as a JSON string. Again, pretty cheap.
  public function to_json ($opts=Null)
  {
    return json_encode($this->to_array($opts));
  }

  // Clear our data.
  public function clear ($opts=array())
  {
    $this->data = array();
  }

  public function append ($item)
  {
    $this->data[] = $item;
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
  }

  public function swap ($pos1, $pos2)
  {
    $new1 = $this->data[$pos2];
    $this->data[$pos2] = $this->data[$pos1];
    $this->data[$pos1] = $new1;
  }

  // Iterator interface.
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

  // ArrayAccess Interface.
  public function offsetExists ($offset)
  {
    return array_key_exists($offset, $this->data);
  }

  public function offfsetGet ($offset)
  {
    if (isset($this->data[$offset]))
      return $this->data[$offset];
  }

  public function offsetSet ($offset, $value)
  {
    $this->data[$offset] = $value;
  }

  public function offsetUnset ($offset)
  {
    unset($this->data[$offset]);
  }

  // Countable interface.
  public function count ()
  {
    return count($this->data);
  }

  // Finally, the is() method is separate from offsetExists.
  public function is ($key)
  {
    return isset($this->data[$key]);
  }

}

