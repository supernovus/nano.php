<?php

namespace Nano3\Loaders;

/** 
 * The base class for loading object-based classes.
 * Useful for controllers, models, etc. Extend as needed.
 */
class ClassLoader extends \Nano3\Loader
{
  /** 
   * The type is a formatted string where %s represents the user-friendly 
   * name of the class that will be returned by the id() function, and
   * is what you use to load the class. The rest is used to generate the
   * full classname.
   */
  protected $type; 

  /**
   * If used, the namespace will be prepended to the class name.
   * You can use this to group all of your application classes into
   * a single namespace. If a constant of NANO_CLASS_PREFIX is found,
   * it will be used globally, otherwise you can override it on each of
   * your loaders using the appropriate options.
   *
   * You can specify mutiple namespace prefixes by separating with a colon.
   */
  protected $namespace;

  public function __construct ($opts)
  {
    if (defined('NANO_CLASS_PREFIX'))
    {
      $this->namespace = explode(':', NANO_CLASS_PREFIX);
    }
    parent::__construct($opts);
  }

  /**
   * Return an array of type strings to check for.
   */
  public function get_types ()
  {
#    error_log("gt//t:'{$this->type}'");
    $typestrings = array();
    if (isset($this->namespace))
    {
      foreach ($this->namespace as $ns)
      {
        $typestrings[] = $ns . $this->type;
      }
    }
    else
    {
      $typestrings[] = $this->type;
    }
    return $typestrings;
  }

  /**
   * Load a requested class.
   */
  public function load ($class, $data=NULL)
  {
    // If dir is unset or false, assume autoloading.
    if ($this->dir)
    {
      parent::load($class);    // First, run the require_once.
    }

    // Now let's build an object and return it.
    $types = $this->get_types();
    foreach ($types as $type)
    {
      $classname = sprintf($type, $class);
#      error_log("n:'$classname', t:'$type', c:'$class'");
      if (class_exists($classname))
        return new $classname ($data);
    
    }

    // If we reached here, we didn't find a class.
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
      $types = $this->get_types();
      foreach ($types as $type)
      {
        $classname = sprintf($type, $class);
        if (class_exists($classname))
        {
          return True;
        }
      }
      return False;
    }
  }

  // Get the identifier of an object, by removing the type string.
  public function id ($object)
  {
    $classname = strtolower(get_class($object));
    $types = $this->get_types();
    foreach ($types as $type)
    {
      $type = str_replace('%s', '', strtolower($type));
      $type = ltrim($type, "\\");
      if (is_numeric(strpos($classname, $type)))
      {
        $classname = str_replace($type, '', $classname);
        break;
      }
    }
    return $classname;
  }

}

