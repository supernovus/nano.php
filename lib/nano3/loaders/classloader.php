<?php

namespace Nano3\Loaders;

/* The base class for loading object-based classes.
   Useful for controllers, models, etc. Exten as needed.
 */
class ClassLoader extends \Nano3\Loader
{
  // The default for objects is to append the type with an underscore.
  // So if 'type' is 'controller' and you load a library called 'test',
  // it will look for 'test.php' in the controllers directory, and expect
  // that file to define a class called 'test_controller' (case insensitive.)
  protected $type;
  public function load ($class, $data=NULL)
  {
    // If dir is unset or false, assume autoloading.
    if ($this->dir)
    {
      parent::load($class);    // First, run the require_once.
    }
    // Now let's build an object and return it.
    $classname = sprintf($this->type, $class);
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
      $classname = sprintf($this->type, $class);
      return class_exists($classname);
    }
  }

  // Get the identifier of an object, strips off the _$type suffix.
  public function id ($object)
  {
    $classname = strtolower(get_class($object));
#    error_log("classname: '$classname'");
    $type = str_replace('%s', '', strtolower($this->type));
    $type = ltrim($type, "\\");
#    error_log("type: '$type'");
    $identifier = str_replace($type, '', $classname);
#    error_log("identifier: '$identifier'");
    return $identifier;
  }

}

