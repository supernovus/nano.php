<?php

namespace Nano\Utils;

/**
 * Browser utils. Not much here yet, but a is_ie() method.
 */

class Browser
{
  public static function is_ie ($ua=null)
  {
    if (!isset($ua))
    {
      $ua = $_SERVER['HTTP_USER_AGENT'];
    }
    if ($ua && preg_match('/MSIE|Trident/i', $ua))
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
  public static function get_ie_ver ($ua=null)
  {
    if (!isset($ua))
    {
      $ua = $_SERVER['HTTP_USER_AGENT'];
    }
    
    if (!$ua) return False;

    preg_match('/MSIE (.*?);/', $ua, $matches);
    if (count($matches) < 2)
    {
      $trident = '/Trident\/\d{1,2}.\d{1,2}; rv:([0-9]*)/';
      preg_match($trident, $ua, $matches);
    }

    if (count($matches) > 1)
    {
      $version = $matches[1];
      return $version;
    }

    return False;
  }

}

