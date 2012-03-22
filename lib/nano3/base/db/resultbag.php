<?php

/**
 * Iterable query results.
 *
 * Plus direct access to the result columns
 * without creating an item object first.
 *
 * In addition to the array-like access, this also
 * provides object-like attributes.
 *
 */

namespace Nano3\Base\DB;

class ResultBag extends ResultArray
{
  protected function offsetGet ($offset)
  {
    $row = current($this->results);
    return $row[$offset];
  }

  protected function offsetExists ($offset)
  {
    $row = current($this->results);
    return isset($row[$offset]);
  }

  protected function __get ($name)
  {
    return $this->offsetGet($name);
  }

  protected function __isset ($name)
  {
    return $this->offsetExists($name);
  }

  protected function __set ($name, $value)
  {
    return $this->offsetSet($name, $value);
  }

  protected function __unset ($name)
  {
    return $this->offsetUnset($name);
  }

}
