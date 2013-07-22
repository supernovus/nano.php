<?php

namespace Nano4\Controllers;

/**
 * Provide a default __construct() method that can chain a bunch of
 * constructors together. 
 *
 * The list of constructors that will be called, and in what order, is
 * dependent on the existence of a class property called $constructors.
 * If the property exists, and is an array, then it is a list of keys,
 * which expect a method called __construct_{$key}_controller() is defined
 * in your class (likely via trait composing.)
 *
 * If the property does not exist, or is not an array, then we will get a list
 * of all methods matching __construct_{word}_controller() and will call them
 * all in whatever order they were defined in. So make sure you load traits
 * in an appropriate order for dependency resolution!
 */

trait Constructor
{
  protected $called_constructors = [];

  protected function constructor ($constructor, $opts=[], $fullname=False)
  {
    if ($fullname)
    {
      $method = $constructor;
    }
    else
    {
      $method = "__construct_{$constructor}_controller";
    }

    if 
    ( isset($this->called_constructors[$method]) 
      && $this->called_constructors[$method]
    ) continue; // Skip already called constructors.

    if (method_exists($this, $method))
    {
      $this->called_constructor[$method] = True;
      $this->$method($opts);
    }
  }

  public function __construct ($opts=[])
  {
    if (property_exists($this, 'constructors') && is_array($this->constructors))
    { // Use a defined list of constructors.
      $constructors = $this->constructors;
      $fullname = False;
    }
    else
    { // Build a list of all known constructors, and call them.
      $constructors = preg_grep('/__construct_\w+_controller/i', 
        get_class_methods($this));
      $fullname = True;
    }
    foreach ($constructors as $constructor)
    {
      $this->constructor($constructor, $opts, $fullname);
    }
  }
}

