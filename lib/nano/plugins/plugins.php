<?php

namespace Nano\Plugins;
use \Nano\Loader;

class Plugins
{
  use Loader\Namespaces, Loader\Instance 
  {
    Loader\Instance::load as load_class;
  }

  public function __construct ($opts=[])
  {
    if (isset($opts['namespace']) && is_array($opts['namespace']))
    {
      $this->namespace = $opts['namespace'];
    }
    else
    {
      $this->namespace = ["\\Nano\\Plugins"];
    }
  }

  public function load ($class, $opts=[])
  {
    $nano = \Nano\get_instance();
    $plugin = $this->load_class($class, $opts);

    if (isset($opts['as']))
      $name = $opts['as'];
    else
      $name = $class;

    $nano->lib[$name] = $plugin;
  }

}
