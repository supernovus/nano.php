<?php

/** 
 * Nano initialization library and core object.
 */

namespace Nano;

/**
 * Populate some options in an array or array-like object based
 * on the contents of a JSON configuration file.
 */
function load_opts_from ($file, &$opts)
{
  if (file_exists($file))
  {
    $conf = json_decode(file_get_contents($file), true);
    foreach ($conf as $ckey => $cval)
    {
      $opts[$ckey] = $cval;
    }
  }
}

/** 
 * Get the output content from a PHP file.
 * This is used as the backend function for all View related methods.
 *
 * @param string $filename    The PHP file to get the content from.
 * @param array  $data        Array of fields to extract into local variables.
 */
function get_php_content ($__view_file, $__view_data=NULL)
{ 
  // First, start saving the buffer.
  ob_start();
  if (isset($__view_data))
  { // First let's see if we have set a local name for the full data.
    if (isset($__view_data['__data_alias']))
    {
      $__data_alias = $__view_data['__data_alias'];
      $$__data_alias = $__view_data;
    }
    if ($__view_data instanceof \ArrayObject)
    {
      extract($__view_data->getArrayCopy());
    }
    elseif (is_array($__view_data))
    {
      extract($__view_data);
    }
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
global $__nano_registered;
$__nano_registered = false;

/**
 * Set up autoloader.
 */
function register ($opts=[])
{
  global $__nano_registered;
  if ($__nano_registered) return;

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
  $__nano_registered = true;
}

/**
 * Unregister. Not sure why you'd want to do this, but anyway.
 */
function unregister ($opts=[])
{
  global $__nano_registered;
  if (isset($opts['all']) && $opts['all'])
  { // Unregister all autoloaders.
    $functions = spl_autoload_functions();
    foreach ($functions as $function)
    {
      spl_autoload_unregister($function);
    }
  }
  else
  { // Unregister the default autoloader.
    spl_autoload_unregister('spl_autoload');
  }
  $__nano_registered = false;
}

/**
 * Create and initialize a Nano object.
 *
 * This also sets up the autoloader unless 'skip_autoload' is true.
 */
function initialize ($opts=[])
{
  global $__nano__instance;
  if (isset($__nano__instance))
    throw new Exception("Nano instance already initialized.");

  if (!isset($opts['skip_autoload']) || !$opts['skip_autoload'])
  {
    register($opts);
  }
  return $__nano__instance = new Nano($opts);
}

/**
 * Get the current Nano instance.
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
    throw new Exception("No Nano instance has been initialized yet.");
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
    $this->lib['plugins'] = new \Nano\Plugins\Plugins();
  }

  /* We're using psuedo-accessors to make life easier. 
   * Properties are mapped to loaded plugin libraries.
   */

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

  /* The ArrayAccess interface is mapped to the options. */

  /**
   * Does the option exist?
   */
  public function offsetExists ($path)
  {
    $get = $this->offsetGet($path);
    if (isset($get))
      return True;
    else
      return False;
  }

  /**
   * Set an option.
   */
  public function offsetSet ($path, $value)
  {
    $tree = explode('.', $path);
    $data = &$this->opts;
    $key  = array_pop($tree);
    foreach ($tree as $part)
    {
      if (!isset($data[$part]))
        $data[$part] = [];
      $data = &$data[$part];
    }
    $data[$key] = $value;
  }

  /**
   * Unset an option.
   */
  public function offsetUnset ($path)
  {
    $tree = explode('.', $path);
    $data = &$this->opts;
    $key  = array_pop($tree);
    foreach ($tree as $part)
    {
      if (!isset($data[$part]))
        return; // A part of the tree doesn't exist, we're done.
      $data = &$data[$part];
    }
    if (isset($data[$key]))
      unset($data[$key]);
  }

  /**
   * Get an option based on a path.
   */
  public function offsetGet ($path)
  {
    $find = explode('.', $path);
    $data = $this->opts;
    foreach ($find as $part)
    {
      if (is_array($data) && isset($data[$part]))
        $data = $data[$part];
      else
        return Null;
    }
    return $data;
  }

  /**
   * Add an extension method. 
   *
   * This function allows extensions to add a method to the Nano object.
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

