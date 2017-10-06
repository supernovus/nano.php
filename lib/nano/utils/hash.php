<?php

namespace Nano\Utils;
use Nano\Exception;

/**
 * A very simplistic wrapper for the PHP Hash library.
 *
 * Usage:
 *
 *  $hash = new Hash('sha256');
 *  $hash->update('Hello');
 *  $hash->update('World');
 *  $bitstr = $hash->final();
 *
 */

class Hash
{
  /**
   * The internal hash object returned from PHP's hash_init().
   */
  protected $hash;

  /**
   * Create a new Hash object.
   *
   * @param str   $algorithm  The hashing algorithm to use.
   * @param int   $options    Any PHP hash() options to use.
   * @param mixed $key        Optional hash key to use.
   */

  public function __construct ($algorithm, $options=0, $key=Null)
  {
    $this->hash = hash_init($algorithm, $options, $key);
  }

  /**
   * Call a hash function.
   *
   * Basically any method not locally defined in this class will call:
   *
   *  hash_{function}($hash, $arg1, ...);
   *
   */
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

  /**
   * Return a base64 encoded string. This finalizes the hash object.
   */
  public function base64 ()
  {
    $binary = $this->final(True);
    $base64 = base64_encode($binary);
    return $base64;
  }

  /**
   * Return a base91 encoded string. This finalizes the hash object.
   */
  public function base91 ()
  {
    $binary = $this->final(True);
    $base91 = Base91::encode($binary);
    return $base91;
  }

  /**
   * Return a binary string (bit string). This finalizes the hash object.
   */
  public function __toString ()
  {
    return $this->final();
  }

} // end of class Nano\Utils\Hash

