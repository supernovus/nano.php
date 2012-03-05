<?php

/**
 * Iterable query results.
 *
 * Plus direct access to the result columns
 * without creating an item object first.
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
}
