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
    parent::load($class);    // First, run the require_once.
    // Now let's build an object and return it.
    $classname = $class.'_'.$this->type; // PHP is not case sensitive.
    if (class_exists($classname))
      return new $classname ($data);
    else
      throw new \Nano3\Exception("No such class: $classname");
  }

  // Get the identifier of an object, strips off the _$type suffix.
  public function id ($object)
  {
    return \Nano3\get_class_identifier('_'.$this->type, $object);
  }

}

