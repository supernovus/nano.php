<?php

namespace Nano3\Utils;
use Nano3\Exception;

/**
 * Common Units operations.
 *
 * Return information about Units, including conversion numbers.
 */

// TODO: Methods for obtaining conversion values, and performing
//       conversions. The methods should exist within the UnitsItem
//       class, and have convenience wrappers in Units.

class Units implements \ArrayAccess, \Countable
{
  protected $classes; // A list of classes.
  protected $units;   // A list of units.

  /**
   * Build a Units object.
   */
  public function __construct ($conf)
  {
    $classes = [];
    $units = [];
    foreach ($conf as $uid => $udef)
    {
      $cid = $udef['class'];
      if (!isset($classes[$cid]))
        $classes[$cid] = new UnitsClass();
      $class = $classes[$cid];
      $unit = new UnitsItem($udef, $class);
      $class[$uid] = $unit;
      $units[$uid] = $unit;
      if (isset($udef['base']) && $udef['base'])
        $class->base = $uid;
      if (isset($udef['step']))
        $class->step = $udef['step'];
    }
    $this->classes = $classes;
    $this->units = $units;
  }

  /**
   * Return the total count of known units.
   */
  public function count ()
  {
    return count($this->units);
  }

  /**
   * Does a class exist?
   */
  public function offsetExists ($offset)
  {
    return isset($this->classes[$offset]);
  }

  /**
   * Get a Class.
   */
  public function offsetGet ($offset)
  {
    if (isset($this->classes[$offset]))
      return $this->classes[$offset];
  }

  /**
   * Classes are read only.
   */
  public function offsetSet ($offset, $value)
  {
    throw new Exception("Cannot set Unit Classes.");
  }

  /**
   * Classes are read only.
   */
  public function offsetUnset ($offset)
  {
    throw new Exception("Cannot unset Unit Classes.");
  }

}

class UnitsClass implements \ArrayAccess, \Countable, \Iterator
{
  public $base;     // The id of our baseline unit (if applicable.)
  public $step;     // Step increment between units (if applicable.)
  protected $units; // A list of units.

  /**
   * Return the total count of units in our class.
   */
  public function count ()
  {
    return count($this->units);
  }

  /**
   * Does a unit exist?
   */
  public function offsetExists ($offset)
  {
    return isset($this->units[$offset]);
  }

  /**
   * Get a Unit
   */
  public function offsetGet ($offset)
  {
    if (isset($this->units[$offset]))
      return $this->units[$offset];
  }

  /**
   * Add a unit to our units.
   */
  public function offsetSet ($offset, $value)
  {
    if ($value instanceof UnitsItem)
      $this->units[$offset] = $value;
    else
      throw new Exception("Invalid Unit item.");
  }

  /**
   * We don't allow unsetting units.
   */
  public function offsetUnset ($offset)
  {
    throw new Exception("Cannot unset Units.");
  }

  // Iterator interface.

  public function current ()
  {
    return current($this->units);
  }

  public function key ()
  {
    return key($this->units);
  }

  public function next ()
  {
    return next($this->units);
  }

  public function rewind ()
  {
    return reset($this->units);
  }

  public function valid ()
  {
    return ($this->current() !== False);
  }

  public function units ()
  {
    return array_keys($this->data);
  }

}

class UnitsItem
{
  public $class; // Our Unit Class.
  public $to;    // Multiply by this to convert this to a base value.
  public $from;  // Multiply by this to convert a base value to this.
  public $prev;  // The unit previous to this one.
  public $next;  // The unit next after this one.
  public $sign;  // The symbol to use for the unit in expressions.

  public function __construct ($opts, $class)
  {
    $this->class = $class;
    foreach (['to','from','prev','next','sign'] as $field)
    {
      if (isset($opts[$field]))
      {
        $this->$field = $opts[$field];
      }
    }
  }

}

