<?php

namespace Nano4\Plugins;
use \Nano4\Loader;

class Pragmas implements \ArrayAccess
{
  use Loader\Files;

  public function __construct ($opts=[])
  {
    if (isset($opts['dirs']))
    {
      $this->dirs = $opts['dirs'];
    }
    else
    {
      $nano = \Nano4\get_instance();
      $root = $nano['classroot'];
      if (!isset($root)) $root = 'lib';
      $dir = $root . '/nano4/pragmas';
      $this->dirs = [$dir];
    }
  }

  public function load ($class, $opts=[])
  {
    $file = $this->find_file($class);
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
