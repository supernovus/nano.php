<?php

namespace Nano\Utils;

/**
 * Base91 encode/decode library.
 *
 * Base91 algorithm copyright (c) 2005-2006 Joachim Henke.
 * Library inspired by PHP code from:
 * https://github.com/jdeastwood/CPRNG/blob/master/CPRNGUI/base91.php
 */
class Base91
{
  /**
   * The encoding table.
   */
  public static $enctab =
  [
  	'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
  	'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
  	'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
  	'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
  	'0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '!', '#', '$',
  	'%', '&', '(', ')', '*', '+', ',', '.', '/', ':', ';', '<', '=',
  	'>', '?', '@', '[', ']', '^', '_', '`', '{', '|', '}', '~', '"'  
  ];

  /**
   * Decode a base91 string.
   *
   * @param str $d  The base91 string to decode.
   *
   * @return str  The decoded byte sequence.
   */
  public static function decode ($d)
  {
    $b91_dectab = array_flip(static::$enctab);
  	$l = strlen($d);
  	$v = -1;
    $b = $n = 0;
    $o = '';
  	for ($i = 0; $i < $l; ++$i) {
  		$c = $b91_dectab[$d{$i}];
  		if (!isset($c))
  			continue;
  		if ($v < 0)
  			$v = $c;
  		else {
  			$v += $c * 91;
  			$b |= $v << $n;
  			$n += ($v & 8191) > 88 ? 13 : 14;
  			do {
  				$o .= chr($b & 255);
  				$b >>= 8;
  				$n -= 8;
  			} while ($n > 7);
  			$v = -1;
  		}
  	}
  	if ($v + 1)
  		$o .= chr(($b | $v << $n) & 255);
  	return $o;    
  }

  /**
   * Encode a byte sequence to base91.
   *
   * @param str $d  The byte sequence to encode.
   *
   * @return str  The base91 encoded string.
   */
  public static function encode ($d)
  {
    $b91_enctab = static::$enctab;
  	$l = strlen($d);
    $b = $n = 0;
    $o = '';
  	for ($i = 0; $i < $l; ++$i) {
  		$b |= ord($d{$i}) << $n;
  		$n += 8;
  		if ($n > 13) {
  			$v = $b & 8191;
  			if ($v > 88) {
  				$b >>= 13;
  				$n -= 13;
  			} else {
  				$v = $b & 16383;
  				$b >>= 14;
  				$n -= 14;
  			}
  			$o .= $b91_enctab[$v % 91] . $b91_enctab[$v / 91];
  		}
  	}
  	if ($n) {
  		$o .= $b91_enctab[$b % 91];
  		if ($n > 7 || $b > 90)
  			$o .= $b91_enctab[$b / 91];
  	}
  	return $o;    
  }
}
