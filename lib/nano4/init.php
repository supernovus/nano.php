<?php

/** 
 * Nano4 Framework Engine
 *
 * A modular, extendible, object-oriented engine for building
 * PHP web applications. This is the 4rd generation engine
 * and requires PHP 5.4 or higher.
 */

namespace Nano4;

/** 
 * Get the output content from a PHP file.
 * This is used as the backend function for all View related methods.
 *
 * @param string $filename    The PHP file to get the content from.
 * @param array  $data        An array of fields to extract into local variables.
 */
function get_php_content ($__view_file, $__view_data=NULL)
{ 
  // First, start saving the buffer.
  ob_start();
  if (isset($__view_data) && is_array($__view_data))
  { // First let's see if we have set a local name for the full data.
    if (isset($__view_data['__data_alias']))
    {
      $__data_alias = $__view_data['__data_alias'];
      $$__data_alias = $__view_data;
    }
    extract($__view_data);
  }
  // Now, let's load that template file.
  include $__view_file;
  // Okay, now let's get the contents of the buffer.
  $buffer = ob_get_contents();
  // Clean out the buffer.
  @ob_end_clean();
  // And return out processed view.
  return $buffer;
}

/** 
 * The base class for Nano Exceptions 
 */
class Exception extends \Exception {}

// Storage for the global Nano object.
global $__nano__instance;

/**
 * Create and initialize a Nano4 object.
 * This also sets up the autoloader unless 'skip_autoload' is true.
 */
function initialize ($opts=[])
{
  if (!isset($opts['skip_autoload']) || !$opts['skip_autoload'])
  {
    if (isset($opts['classroot']))
    {
      $classroot = $opts['classroot'];
    }
    else
    {
      $classroot = 'lib';
    }

    set_include_path(get_include_path().PATH_SEPARATOR.$classroot);
    spl_autoload_extensions('.php');
    spl_autoload_register('spl_autoload');
  }

  // If 'no' => true, we don't build a Nano object.
  if (isset($opts['no']) && $opts['no']) return;

  global $__nano__instance;
  if (isset($__nano__instance))
    throw new Exception("Nano4 instance already initialized.");

  return $__nano__instance = new Nano($opts);
}

/**
 * Get the current Nano4 instance.
 */
function get_instance ()
{
  global $__nano__instance;
  if (isset($__nano__instance))
  {
    return $__nano__instance;
  }
  else
  {
    throw new Exception("No Nano4 instance has been initialized yet.");
  }
}

/** 
 * The base class for the Nano framework.
 * Either make an instance of this directly, or make your own base
 * class that extends this. As per the notice above, you cannot have
 * more than one instance of a Nano-derived object at one time.
 */
class Nano implements \ArrayAccess
{
  public $lib     = [];    // Library objects.
  public $methods = [];    // Extension methods (callbacks.)
  public $opts    = [];    // Options passed to the constructor.

  /**
   * Construct a new Nano object. By default we build a 'nano' loader which
   * loads Nano extensions from our own folder. You can override the folder
   * of the loader via the 'nanodir' option to the constructor.
   * This loader is expected to be available by other extensions, so don't
   * mess with it. Oh, and it's a basic constructor, so it only loads
   * libraries, nothing more, nothing less.
   */
  public function __construct ($opts=[])
  {
    // Save the options for later reference.
    $this->opts = $opts;
    // Initialize the Plugins plugin with all of its defaults.
    $this->lib['plugins'] = new \Nano4\Plugins\Plugins();
  }

  /* We're using psuedo-accessors to make life easier */

  /**
   * See if we have a library loaded already.
   */
  public function __isset ($offset)
  {
    return isset($this->lib[$offset]);
  }

  /**
   * Use like: $nano->targetname = 'pluginname';
   * or:       $nano->targetname = ['plugin'=>$name, ...];
   */
  public function __set($offset, $value)
  {
    if (strtolower($offset) == 'plugins')
    {
      throw new Exception("Cannot overwrite 'plugins' plugin.");
    }
    if (is_array($value))
    {
      $opts  = $value;
      if (isset($value['plugin']))
      {
        $class = $value['plugin'];
        $opts['as'] = $offset;
      }
      else
      {
        $class = $offset;
      }
    }
    elseif (is_string($value))
    {
      $class = $value;
      $opts  = ['as'=>$offset];
    }
    else
    {
      throw new Exception("Unsupported library load value");
    }

    $this->lib['plugins']->load($class, $opts);
  }

  /**
   * Not recommended, but no longer forbidden, except for 'plugins'.
   */
  public function __unset ($offset)
  {
    if (strtolower($offset) == 'plugins')
    {
      throw new Exception("Cannot remove the 'plugins' plugin.");
    }
    unset($this->lib[$offset]);
  }

  /**
   * Get a library object from our collection.
   * This supports autoloading plugins using the 'plugins' plugin.
   */
  public function __get ($offset)
  {
    if (isset($this->lib[$offset]))
    {
      return $this->lib[$offset];
    }
    elseif ($this->lib['plugins']->is($offset))
    { // A plugin matched, let's load it.
      $this->lib['plugins']->load($offset);
      return $this->lib[$offset];
    }
    else
    {
      throw new Exception("Invalid Nano attribute called.");
    }
  }

  /* Plus the ArrayAccess interface for extra flexibility. */

  /**
   * Alias to __isset()
   */
  public function offsetExists ($name)
  {
    return $this->__isset($name);
  }

  public function offsetSet ($name, $value)
  {
    return $this->__set($name, $value);
  }

  public function offsetUnset ($name)
  {
    return $this->__unset($name);
  }

  /**
   * Alias to __get()
   */
  public function offsetGet ($name)
  {
    return $this->__get($name);
  }

  /**
   * Add an extension method. 
   *
   * This function allows extensions to add a method to the Nano4 object.
   * 
   * @param string $name      The name of the method we are adding.
   * @param mixed  $callback  A callback, with exceptions, see below.
   *
   * If the callback parameter is a string, then the normal PHP callback
   * rules are ignored, and the string is assumed to be the name of a
   * library object that provides the given method (the method in the library
   * must be the same name, and must be public.)
   *
   * Class method calls, object method calls and closures are handled
   * as per the standard PHP callback rules.
   */
  public function addMethod ($name, $callback)
  {
    $this->methods[$name] = $callback;
  }

  /**
   * The __call() method looks for extension methods, and calls them.
   */
  public function __call ($method, $arguments)
  {
    // First we check for extension methods.
    if (isset($this->methods[$method]))
    {
      if (is_string($this->methods[$method]))
      { // A string is assumed to be the name of a library.
        // We don't support plain function callbacks.
        $libname  = $this->methods[$method];
        $libobj   = $this->lib[$libname];
        $callback = array($libobj, $method);
      }
      else
      { // Anything else is considered the callback itself.
        // Which can be a class method call, object method call or closure.
        $callback = $this->methods[$method];
      }
      // Now let's dispatch to the callback.
      return call_user_func_array($callback, $arguments);
    }

    // If we reached this far, we didn't find any methods.
    throw new Exception("Unhandled method '$method' called.");
  }

}

// End of library.

