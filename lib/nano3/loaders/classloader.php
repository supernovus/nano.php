<?php

namespace Nano3\Loaders;

/* The base class for loading object-based classes.
   Useful for controllers, models, etc. Exten as needed.
 */
class ClassLoader extends \Nano3\Loader
{
  // The type is a formatted string where %s represents the user-friendly 
  // name of the class that will be returned by the id() function, and
  // is what you use to load the class. The rest is used to generate the
  // full classname.
  protected $type; 

  // If used, the namespace will be prepended to the class name.
  // You can use this to group all of your application classes into
  // a single namespace. If a constant of NANO_CLASS_PREFIX is found,
  // it will be used globally, otherwise you can override it on each of
  // your loaders using the appropriate options.
  protected $namespace;

  public function __construct ($opts)
  {
    if (defined('NANO_CLASS_PREFIX'))
    {
      $this->namespace = NANO_CLASS_PREFIX;
    }
    parent::__construct($opts);
  }

  public function get_type ()
  {
    if (isset($this->namespace))
    {
      return $this->namespace . $this->type;
    }
    else
    {
      return $this->type;
    }
  }

  public function load ($class, $data=NULL)
  {
    // If dir is unset or false, assume autoloading.
    if ($this->dir)
    {
      parent::load($class);    // First, run the require_once.
    }
    // Now let's build an object and return it.
    $classname = sprintf($this->get_type(), $class);
    if (class_exists($classname))
      return new $classname ($data);
    else
      throw new \Nano3\Exception("No such class: $classname");
  }

  public function is ($class)
  {
    if (isset($this->dir))
    {
      return parent::is($class);
    }
    else
    {
      $classname = sprintf($this->get_type(), $class);
      return class_exists($classname);
    }
  }

  // Get the identifier of an object, by removing the type string.
  public function id ($object)
  {
    $classname = strtolower(get_class($object));
    $type = str_replace('%s', '', strtolower($this->get_type()));
    $type = ltrim($type, "\\");
    $identifier = str_replace($type, '', $classname);
    return $identifier;
  }

}

