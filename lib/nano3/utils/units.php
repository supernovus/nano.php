<?php

namespace Nano3\Utils;
use Nano3\Exception;

/**
 * Common Units operations.
 *
 * Return information about Units, including conversion numbers.
 */

trait HasUnits
{
  protected $units;

  public function convert ($value, $from, $to)
  {
    if (is_string($from))
    {
      $from = $this->units[$from];
    }
    if (isset($from) && $from instanceof UnitsItem)
    {
      return $from->convert($value, $to);
    }
    throw new Exception("Invalid 'from' unit passed to convert()");
  }
}

class Units implements \ArrayAccess, \Countable
{
  use HasUnits;

  protected $classes; // A list of classes.

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
  use HasUnits;

  public $base;     // The id of our baseline unit (if applicable.)
  public $step;     // Step increment between units (if applicable.)

  /**
   * Return the base item class.
   */
  public function base ()
  {
    if (isset($this->base))
    {
      return $this->units[$this->base];
    }
    throw new Exception("No base unit defined");
  }

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
  public $pos;   // The position, for a stepping unit.
  public $sign;  // The symbol to use for the unit in expressions.

  public function __construct ($opts, $class)
  {
    $this->class = $class;
    foreach (['to','from','pos','sign'] as $field)
    {
      if (isset($opts[$field]))
      {
        $this->$field = $opts[$field];
      }
    }
  }

  public function to_base ($value)
  {
    if (isset($this->to))
    {
      return $value * $this->to;
    }
    return $value;
  }

  public function from_base ($value)
  {
    if (isset($this->from))
    {
      return $value * $this->from;
    }
    return $value;
  }

  public function convert ($value, $unit)
  {
    if (is_string($unit))
    {
      $unit = $this->class[$unit];
    }
    if (isset($unit) && $unit instanceof UnitsItem)
    {
      if (isset($this->pos))
      {
        if (isset($this->class->step))
        {
          $step = $this->class->step;
          $pos1 = $this->pos + 1;
          $pos2 = $unit->pos + 1;
          if ($pos1 == $pos2)
          { // It's the same unit.
            return $value;
          }
          if ($pos1 > $pos2)
          { // We're converting into a larger unit.
            $diff = $pos1 - $pos2;
            $conv = pow($step, $diff);
            return $value / $conv;
          }
          else
          { // We're converting into a smaller unit.
            $diff = $pos2 - $pos1;
            $conv = pow($step, $diff);
            return $value * $conv;
          }
        }
        throw new Exception("No 'step' defined");
      }
      else
      { // We're using to/from conversions.
        $base = $this->to_base($value);
        $base_unit = $this->class->base();
        if ($base_unit === $unit)
          return $base;
        return $unit->from_base($base);
      }
    }
    throw new Exception("Invalid 'to' unit passed to convert()");
  }
}

