<?php

namespace Nano\Utils;

/**
 * A helper class for common language related functions.
 */

class Language
{

  /**
   * Parse an HTTP Accept-Language header and return a sorted array.
   */
  static function accept ($langs=Null)
  {
    if (!isset($langs))
      $lang_string = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $lang_string, $lang_parse);

    if (count($lang_parse[1]))
    {
      $langs = array_combine($lang_parse[1], $lang_parse[4]);

      foreach ($langs as $lang => $val)
      {
        if ($val === '') $langs[$lang] = 1;
      }

      arsort($langs, SORT_NUMERIC);
    }
    return $langs;
  }
}
