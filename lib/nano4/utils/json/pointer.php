<?php

namespace Nano4\Utils\JSON;

/**
 * A minimalistic implementation of the JSON Pointer specification for PHP.
 *
 * Borrowed bits from php-jsonpointer by Raphael Stolt, and bits from
 * JSON-Patch-PHP by Mike McCabe. Adapted for my own uses.
 */
class Pointer
{
  /**
   * @var mixed  Our document data (either an array or object).
   */
  protected $data;

  /**
   * @var string  The current pointer.
   */
  protected $pointer;

  public function __construct ($data)
  {
    if (is_array($data) || is_object($data))
    {
      $this->data = $data;
    }
    elseif (is_string($data))
    {
      $this->data = json_decode($data, true);
      if (json_last_error() !== JSON_ERROR_NONE)
      {
        throw new Exception\InvalidJSON("Cannot operate on invalid JSON");
      }
      if (!is_array($this->data))
      {
        throw new Exception\InvalidData("Cannot traverse the decoded JSON");
      }
    }
    else
    {
      throw new Exception\InvalidData("Unsupported data sent to ".__CLASS__);
    }
  }

  public function get ($pointer)
  {
    if ($pointer === '')
    {
      return $this->data;
    }
    $this::validatePointer($pointer);
    $this->pointer = $pointer;
    $parts = $this::getParts($pointer);
    return $this->traverse($this->data, $parts);
  }

  public static function getParts ($pointer)
  {
    $parts = explode('/', $pointer);
    for ($i = 0; $i < count($parts); $i++)
    {
      $parts[$i] = str_replace('~1', '/', $parts[$i]);
      $parts[$i] = str_replace('~0', '~', $parts[$i]);
    }
    return $parts;
  }

  protected static function validatePointer ($pointer)
  {
    if (!is_string($pointer))
    {
      throw new Exception\InvalidPointer("Pointer is not a string");
    }
    $firstChar = substr($pointer, 0, 1);
    if ($firstChar !== '/')
    {
      throw new Exception\InvalidPointer("Pointers must start with /");
    }
  }

  protected function traverse (&$data, $parts)
  {
    $part = array_shift($parts);
    $found = false;
    $want = null;
    if (isset($data[$part]))
    { // Value explicitly exists.
      $want = $data[$part];
      $found = true;
    }
    elseif ($part === '-')
    { // We want the end of an array.
      if (is_array($data))
      {
        $want = end($data);
        $found = true;
      }
      elseif (is_object($data) && is_callable([$data, 'end']))
      {
        $want = $data->end();
        $found = true;
      }
    }
    elseif (is_array($data) && array_key_exists($part, $data))
    { // A 'null' value in an array.
      $want = $data[$part];
      $found = true;
    }
    elseif (is_object($data) && property_exists($data, $part))
    { // A 'null' property in an object.
      $want = $data->$part;
      $found = true;
    }

    if ($found)
    {
      if (count($parts) === 0)
      {
        return $want;
      }
      else
      {
        return $this->traverse($want, $parts);
      }
    }
    $msg = sprintf(
      "Pointer '%s' references a nonexistent value '%s'",
      $this->pointer,
      $part
    );
    throw new Exception\NonexistentProperty($msg);
  }

}