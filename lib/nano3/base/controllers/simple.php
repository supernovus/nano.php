<?php

namespace Nano3\Base\Controllers;

/**
 * Simple controller.
 *
 * Adds an array-like interface to the Basic controller for easier
 * handling of View template data.
 */

abstract class Simple extends Basic implements \ArrayAccess
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
