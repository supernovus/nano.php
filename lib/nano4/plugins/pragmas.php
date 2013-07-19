<?php

namespace Nano4\Plugins;
use \Nano4\Loader;

class Pragmas
{
  use Loader\Files;

  public function __construct ($opts=[])
  {
    if (isset($opts['dir']))
    {
      $this->dir = $opts['dir'];
    }
    else
    {
      $nano = \Nano4\get_instance();
      $root = get_include_path();
      $this->dir = $root . PATH_SEPARATOR . 'pragmas';
    }
  }

  public function load ($class, $opts=[])
  {
    $file = $this->find($class);
    if (isset($file))
    {
      require_once $file;
    }
  }

  public function offsetSet ($o, $v)
  {
    throw new \Nano4\Exception("Cannot set a pragma");
  }

  public function offsetExists ($o)
  {
    return $this->is($o);
  }

  public function offsetUnset ($o)
  {
    throw new \Nano4\Exception("Cannot unset a pragma");
  }

  public function offsetGet ($o)
  {
    $pragmas = explode(' ', $o);
    foreach ($pragmas as $pragma)
    {
      $this->load($pragma);
    }
  }

  public function __get ($o)
  {
    $pragma = str_replace('_', '-', $o);
    return $this->load($pragma);
  }

}
