<?php

namespace Nano\Loader;

/** 
 * A Loader Type Trait that loads an object instance from a class.
 */
trait Instance
{
  /**
   * Load a requested class. Note for our purposes, all classes will
   * be passed an array of options in the constructor. No other constructor
   * format is supported, so use this method.
   */
  public function load ($class, $opts=[])
  {
#    error_log("Instance::load($class, ".json_encode($opts).")");
    // Now let's build an object and return it.
    $classname = $this->find_class($class);
    if (isset($classname))
    {
      $opts['__classid'] = $class;
      return new $classname ($opts);
    }

    // If we reached here, we didn't find a class.
    throw new \Nano\Exception("No such class: $class");
  }
}

