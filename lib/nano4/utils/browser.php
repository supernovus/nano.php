<?php

namespace Nano4\Utils;

/**
 * Browser utils. Not much here yet, but a is_ie() method.
 */

class Browser
{
  public static function is_ie ()
  {
    if (preg_match('/MSIE/i', $_SERVER['HTTP_USER_AGENT']))
    {
      return True;
    }
    return False;
  }
}

