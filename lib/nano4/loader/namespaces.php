<?php

namespace Nano4\Loader;

/** 
 * A Loader Provider Trait that looks for PHP classes in Namespaces.
 *
 * This expects that an Autoloader is in use.
 */
trait Namespaces
{
  /**
   * You MUST specify at least one namespace, and it MUST be an array.
   */
  protected $namespace = [];

  /** 
   * A default constructor. If you override it, don't forget to
   * call the __construct_namespace() method.
   */
  public function __construct ($opts=[])
  {
    $this->__construct_namespace($opts);
  }

  /**
   * A constructor call that builds our namespace.
   */
  public function __construct_namespace ($opts=[])
  {
    if (isset($opts['namespace']) && is_array($opts['namespace']))
    {
      $this->namespace = $opts['namespace'];
    }
  }

  public function is ($classname)
  {
    $class = $this->find_class($classname);
    return isset($class);
  }

  public function find_class ($classname)
  {
    // Replace '.' with '\\' for nested module names.
    $classname = str_replace('.', "\\", $classname);
    foreach ($this->namespace as $ns)
    {
      $class = $ns . "\\" . $classname;
#      error_log("Looking for $class");
      if (class_exists($class))
      {
        return $class;
      }
    }
#    error_log(" -- Not found!");
  }

  public function find_file ($classname)
  {
    $class = $this->find_class($classname);
    if (isset($class))
    {
      $reflector = new ReflectionClass($class);
      return $reflector->getFileName();
    }
  }

  // Add namespaces to search.
  public function addNS ($ns, $top=False)
  {
    if ($top)
    {
      if (is_array($ns))
        array_splice($this->namespace, 0, 0, $ns);
      else
        array_unshift($this->namespace, $ns);
    }
    else
    {
      if (is_array($ns))
        array_splice($this->namespace, -1, 0, $ns);
      else
        $this->namespace[] = $ns;
    }
  }

}

