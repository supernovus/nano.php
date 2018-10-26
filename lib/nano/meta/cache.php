<?php

namespace Nano\Meta;

trait Cache
{
  protected $_nano_cache = [];

  public function cache ($key, $value=null)
  {
    if (isset($this->_nano_cache))
    {
      if (isset($value))
      { // Caching a value.
        $cache = &$this->_nano_cache;
        if (is_scalar($key))
        {
          $cache[$key] = $value;
        }
        elseif (is_array($key))
        {
          $lastkey = array_pop($key);
          foreach ($key as $k)
          {
            if (!isset($cache[$k]))
            {
              $cache[$k] = [];
            }
            $cache = &$cache[$k];
          }
          $cache[$lastkey] = $value;
        }
      }
      else
      { // Retreive a cached value.
        $cache = $this->_nano_cache;
        if (is_scalar($key) && isset($cache[$key]))
        {
          return $cache[$key];
        }
        elseif (is_array($key))
        {
          foreach ($key as $k)
          {
            if (isset($cache[$k]))
            {
              $cache = $cache[$k];
            }
            else
            { // Nothing to return.
              return;
            }
          }
          return $cache;
        }
      }
    }
  }
}