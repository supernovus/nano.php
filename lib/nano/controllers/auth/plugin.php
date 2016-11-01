<?php

namespace Nano\Controllers\Auth;

abstract class Plugin
{
  protected $parent;
  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
      $this->parent = $opts['parent'];
  }
  abstract public function options ($conf);
  abstract public function getauth ($conf, $opts=[]);
}

