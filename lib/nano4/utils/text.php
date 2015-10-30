<?php

namespace Nano4\Utils;

class Text
{
  static function make_identifier ($string, $maxlen=null, $threshold=5)
  {
    $ident = preg_replace('/[^A-Za-z_0-9]*/', '', preg_replace('/\s+/', '_', $string));

    if (isset($maxlen) && is_numeric($maxlen) && $maxlen > 0)
    {
      $len = strlen($ident);

      if ($len > $maxlen)
      {
        if ($len > ($maxlen + $threshold))
        {
          $size = (($maxlen/2)-1);
          $str1 = substr($ident, 0, $size);
          $size *= -1;
          $str2 = substr($ident, $size);
          $ident = $str1 . '__' . $str2;
        }
        else
        {
          $ident = substr($ident, 0, $maxlen);
        }
      }
    }
    
    return $ident;
  }
}

