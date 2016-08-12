<?php

namespace Nano4\Utils;

class Text
{
  static function make_identifier ($string, $maxlen=null, $threshold=5)
  {
    $ident = preg_replace('/[^A-Za-z_0-9]*/', '', 
             preg_replace('/[\s\.\-]+/',      '_', 
             $string));

    if (isset($maxlen) && is_numeric($maxlen) && $maxlen > 0)
    {
      $ident = self::truncate($ident, $maxlen, $threshold, '__');
    }
    
    return $ident;
  }

  static function truncate ($string, $maxlen, $threshold=5, $join='...')
  {
    $len = strlen($string);
    if ($len > $maxlen)
    {
      if ($len > ($maxlen + $threshold))
      {
        $jlen = strlen($join);
        $offset = 1;      // base offset from 0.
        $offset += $jlen; // offset includes size of join string.
        $size = floor((($maxlen/2)-$offset));
        $str1 = substr($string, 0, $size);
        $size *= -1;
        $str2 = substr($string, $size);
        $string = $str1 . $join . $str2;
      }
      else
      {
        $string = substr($string, 0, $maxlen);
      }
    }
    return $string;
  }
}

