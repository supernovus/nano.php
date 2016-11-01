<?php

namespace Nano\Utils;
use Nano\Exception;

/**
 * A very simplistic wrapper for the PHP Hash library.
 *
 * In addition to the normal hash methods, it provides a base64() method which
 * will return a base64 representation of the binary Hash digest.
 *
 * Usage:
 *
 *  $hash = new Hash('sha256');
 *  $hash->update('Hello');
 *  $hash->update('World');
 *  $str = $hash->final();
 *
 */

class Hash
{
  protected $hash;
  public function __construct ($algorithm, $options=0, $key=Null)
  {
    $this->hash = hash_init($algorithm, $options, $key);
  }
  public function __call ($name, $arguments)
  {
    $func = "hash_$name";
    if (function_exists($func))
    {
      array_unshift($arguments, $this->hash);
      return call_user_func_array($func, $arguments);
    }
    else
    {
      throw new Exception("No such method '$name' in Hash class.");
    }
  }
  public function base64 ()
  {
    $binary = $this->final(True);
    $base64 = base64_encode($binary);
    return $base64;
  }
  public function __toString ()
  {
    return $this->final();
  }

} // end of class Nano\Utils\Hash


