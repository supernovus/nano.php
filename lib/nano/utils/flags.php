<?php

namespace Nano\Utils;

class Flags
{
  
/** 
 * Add or remove a flag to a given binary mask.
 *
 * @param integer $flags   The bitmask representing the currently set flags.
 * @param integer $flag    The value of the flag to add or remove.
 * @param boolean $action  If true, we add the flag, if false, we remove the flag.
 *                         Defaults to true.
 */
  static function set_flag (&$flags, $flag, $value=true)
  {
    if ($value)
      $flags = $flags | $flag;
    else
      $flags = $flags - ($flags & $flag);
  }

}

