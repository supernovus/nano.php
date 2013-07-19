<?php

namespace Nano4\Loader;

/** 
 * A Loader Provider Trait that looks for PHP classes in Namespaces.
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

  public function is ($class)
  {
#    error_log("namespace is: ".json_encode($this->namespace));
    foreach ($this->namespace as $ns)
    {
      $classname = $ns . "\\" . $class;
      if (class_exists($classname))
      {
        return True;
      }
    }
    return False;
  }

  public function find ($class)
  {
    foreach ($this->namespace as $ns)
    {
      $classname = $ns . "\\" . $class;
      if (class_exists($classname))
      {
        return $classname;
      }
    }
  }

  public function find_class ($class)
  { // This is simply a call to find().
    return $this->find($class);
  }

  // Add a new namespace.
  public function addNS ($ns, $top=False)
  {
    if ($top)
    {
      array_unshift($this->namespace, $ns);
    }
    else
    {
      $this->namespace[] = $ns;
    }
  }

  // Get the identifier of an object.
  public function class_id ($object)
  {
    $classname = strtolower(get_class($object));
    $pathspec  = explode("\\", $classname);
    return array_pop($pathspec);
  }

}

