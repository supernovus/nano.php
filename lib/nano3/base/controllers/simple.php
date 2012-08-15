<?php

namespace Nano3\Base\Controllers;

/**
 * Simple controller.
 *
 * It's the Basic controller, with an ArrayAccess interface to make working with
 * the data for views a lot easier.
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
