<?php

namespace Nano4\DB;

/**
 * A trait providing a default to_array() method for Result* classes.
 */
trait ResultToArray
{
  /**
   * Create a flat array out of our data.
   */
  public function to_array ($opts=[])
  {
    $array = [];
    foreach ($this as $that)
    {
      if (is_object($that) && is_callable([$that, 'to_array']))
        $item = $that->to_array($opts);
      else
        $item = $that;
      $array[] = $item;
    }
    return $array;
  }
}

