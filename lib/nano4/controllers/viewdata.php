<?php

namespace Nano4\Controllers;

/**
 * Adds an array-like interface to your controller for easier
 * handling of View template data. 
 *
 * You MUST declare your class to implement \ArrayAccess for this to work.
 */

trait ViewData
{
  public function offsetExists ($offset)
  {
    return isset($this->data[$offset]);
  }

  public function offsetGet ($offset)
  {
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
}

