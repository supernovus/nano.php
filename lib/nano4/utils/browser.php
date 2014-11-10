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

  /**
   * Returns a number if it is a valid IE version.
   *
   * Returns False if the browser is not IE.
   */
  public static function get_ie_ver ()
  {
    preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
    if (count($matches) < 2)
    {
      $trident = '/Trident\/\d{1,2}.\d{1,2}; rv:([0-9]*)/';
      preg_match($trident, $_SERVER['HTTP_USER_AGENT'], $matches);
    }

    if (count($matches) > 1)
    {
      $version = $matches[1];
      return $version;
    }

    return False;
  }

}

