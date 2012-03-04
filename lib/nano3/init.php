<?php

/* Nano3 Framework Engine
   A modular, extendible, object-oriented engine for building
   PHP web applications. This is the 3rd generation engine
   and requires PHP 5.3 or higher.
 */

namespace Nano3;

if (!defined('CLASS_ROOT_DIR'))
{
  define('CLASS_ROOT_DIR', 'lib'); // We expect libraries to be in ./lib
}

set_include_path(get_include_path().PATH_SEPARATOR.CLASS_ROOT_DIR);
spl_autoload_extensions('.php');
spl_autoload_register();

// Get the output content from a PHP file.
function get_php_content ($__view_file, $__view_data=NULL)
{ 
  // First, start saving the buffer.
  ob_start();
  if (isset($__view_data) && is_array($__view_data))
  { // If we have data, add it to the local namespace.
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

// Add or remove a flag to a given binary mask.
function set_flag (&$flags, $flag, $value=true)
{
  if ($value)
    $flags = $flags | $flag;
  else
    $flags = $flags - ($flags & $flag);
}

/* The base class for Nano Exceptions */
class Exception extends \Exception {}

// The following can be used to find the Nano object.
// If none exists, it will create a new one.
// However this does not allow you to set any constructor options.
global $__nano__instance;
function get_instance ()
{
  global $__nano__instance;
  if (isset($__nano__instance))
  {
    return $__nano__instance;
  }
  else
  {
    return new Nano3();
  }
}

/* The base class for Nano loaders. 
   Extend this as needed. The default loads the library and nothing more.
   Useful for helper engines such as Nano extensions.
 */
class Loader
{ 
  public $dir;               // The directory which contains our classes.
  public $ext = '.php';      // The file extension for classes (.php)
  public function __construct($opts)
  {
    if (isset($opts) && is_array($opts))
    { 
      foreach ($opts as $opt=>$val)
      {
        if (property_exists($this, $opt))
          $this->$opt = $val;
      }
    }
    else
      throw new Exception('Invalid opts passed to NanoLoader contructor.');
  }
  // Return the filename associated with the given class.
  public function file ($class)
  {
    if (isset($this->dir))
      return $this->dir . '/' . $class . $this->ext;
    else
      return Null;
  }
  // Does the given class exist?
  public function is ($class)
  {
    if (isset($this->dir))
    {
      $file = $this->file($class);
      return file_exists($file);
    }
    return Null;
  }
  // Load the given class. Override this as needed.
  public function load ($class, $data=NULL)
  {
    if (isset($this->dir))
    {
      $file = $this->file($class);
      require_once $file;
    }
    else
    {
      throw new Exception('Attempt to use load() on a Loader with no dir set.');
    }
  }
}

/* The base class for the Nano framework.
   Either make an instance of this directly, or make your own base
   class that extends this. As per the notice above, you cannot have
   more than one instance of a Nano-derived object at one time.
 */

class Nano3 implements \ArrayAccess
{
  public $lib   = array();    // Library objects.
  public $hooks = array();    // Named hooks (callbacks.)
  public $meths = array();    // Extension methods (callbacks.)

  public $call_meths = False;  // Enable extension methods?
  public $call_hooks = False;  // Unknown methods dispatch to hooks?
  public $call_load  = False;  // Dispatch load* methods to loaders?

  // Add a library object to Nano. 
  public function addLib ($name, $class, $opts=NULL)
  { // Override default opts if a conf is found.
    if (
      isset($this->lib['conf']) 
      && 
      (
           is_null($opts) 
        || ( isset($opts['is_default']) && $opts['is_default'] )
      )
    )
    { // This is expected to the Nano3\Conf object,
      // or at least offer an ArrayAccess interface.
      $conf = $this->lib['conf'];
      if (isset($conf[$name]))
      {
        $opts = $conf[$name];
      }
    }
    if (is_object($class))
      $this->lib[$name] = $class;
    else
    {
      $lib = new $class($opts);
      $this->lib[$name] = $lib;
    }
  }

  // Add a loader.
  public function addLoader ($name, $opts, $type=Null)
  {
    // If $opts is not an array, assume it is the 'dir' opt.
    if (!is_array($opts))
    {
      $dir = $opts;
      $opts = array();
      if (isset($dir))
        $opts['dir'] = $dir;
    }

    if (isset($type))
    { // We're using a custom loader class.
      $class = "\\Nano3\\Loaders\\{$type}Loader";
    }
    else
    { // Standard loader.
      $class = '\Nano3\Loader';
    }
    $this->addLib($name, $class, $opts);
  }

  // Add a NanoClassLoader library.
  public function addClass ($name, $type, $opts=array())
  {
    $opts['type'] = $type;
    $this->addLoader($name, $opts, 'Class');
  }

  // Add a NanoViewLoader library.
  public function addViews ($name, $dir, $opts=array())
  {
    $opts['dir'] = $dir;
    $this->addLoader($name, $opts, 'View');
  }

  // Construct a new Nano object. By default we build a 'nano' loader which
  // loads Nano extensions from our own folder. You can override the folder
  // of the loader via the 'nanodir' option to the constructor.
  // This loader is expected to be available by other extensions, so don't
  // mess with it. Oh, and it's a basic constructor, so it only loads
  // libraries, nothing more, nothing less.
  public function __construct ($opts=array())
  {
    global $__nano__instance;
    if (isset($__nano__instance))
      throw new Exception('Nano instance already created.');

    // See if we've overridden the nano library folder.
    if (isset($opts['nanodir']))
      $nano_dir = $opts['nanodir'];
    else
      $nano_dir = dirname(__FILE__);

    // Now register this as the 'nano' loader.
    $this->addLoader('nano', $nano_dir);

    if (isset($opts['conf']))
    {
      if (is_array($opts['conf']))
      {
        $confopts = $opts['conf'];
      }
      else
      {
        $confopts = array('file'=>$opts['conf']);
      }
      $this->addLib('conf', '\Nano3\Conf', $confopts);
    }

    // Set the global variable so we can find this later.
    $__nano__instance = $this;

  }

  // Pragmas change the behavior of the current script.
  public function pragma ($name)
  {
    $this->lib['nano']->load("pragmas/$name");
  }

  // Meta extensions add features to Nano3 itself.
  // This is used by the Nano3 library loader (see __get() for details.)
  public function extend ($name)
  {
    $this->lib['nano']->load("meta/$name");
  }

  // Nano Plugins. Add pre-created library objects to Nano3.
  // This is used by the Nano3 library loader (see __get() for details.)
  public function addPlugin ($name)
  {
    $class = "\\Nano3\\Plugins\\$name";
    $this->addLib($name, $class);
  }

  /* We're using psuedo-accessors to make life easier */

  public function __isset ($offset)
  {
    return isset($this->lib[$offset]);
  }

  public function __set($offset, $value)
  {
    throw new Exception("Use methods to add extensions to Nano.");
  }

  public function __unset ($offset)
  {
    throw new Exception("You cannot remove Nano extensions.");
  }

  public function __get ($offset)
  {
    if (isset($this->lib[$offset]))
      return $this->lib[$offset];
    elseif ($this->lib['nano']->is("plugins/$offset"))
    { // Load a core plugin.
      $this->addPlugin($offset);
      return $this->lib[$offset];
    }
    elseif ($this->lib['nano']->is("meta/$offset"))
    { // Load a meta extension.
      $this->extend($offset);
      return $this->lib[$offset];
    }
    else
      throw new Exception("Invalid Nano attribute called.");
  }

  /* Plus the ArrayAccess interface for extra flexibility. */

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

  public function offsetGet ($name)
  {
    return $this->__get($name);
  }

  /**
   * Hooks.
   *
   * A hook can be placed anywhere in your code.
   * Other pieces of code can add callbacks to be called
   * when that hook is called. Hooks can return data,
   * which will be saved in an array of results.
   *
   * The code calling the hooks specifies the interface to
   * the hooks, so any hook handlers added to that hook need
   * to implement that interface.
   *
   */
  public function addHook ($name, $callback)
  {
    if (!array_key_exists($name, $this->hooks))
    {
      $this->hooks[$name] = array();
    }
    array_push($this->hooks[$name], $callback);
  }

  public function callHook ($name, $opts=array())
  { // We always return an array.
    $hook_out = array();
    if (isset($this->hooks[$name]))
    { // A hook exists, let's call any registered hooks.
      foreach ($this->hooks[$name] as $hook)
      {
        $hook_out = call_user_func_array($hook, $opts);
      }
    }
    return $hook_out;
  }

  /**
   * Add an extension method. 
   *
   * This function allows extensions to add a method to the Nano3 object.
   * The method will only be usable if the Nano3 instance has the
   * 'call_meths' attribute set to True.
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
   *
   */
  public function addMethod ($name, $callback)
  {
    $this->meths[$name] = $callback;
  }

  /**
   * The __call() method does a bit of magic.
   *
   * If $call_meths is True, then we look in our list of extension methods
   * and dispatch to a matching one if found.
   *
   * If $call_hooks is True, then we look for a hook matching the method
   * name called. If we find one, we dispatch to callHook().
   *
   * If $call_load is True, and the method name starts with the word 'load' it
   * looks for a loader by the same name. 
   * Unlike Nano2 there is no pluralizing, so if the loader is called 
   * 'controllers' then loadControllers() must be used not loadController().
   *
   */
  public function __call ($method, $arguments)
  {
    // First we check for extension methods.
    if ($this->call_meths && isset($this->meths[$method]))
    {
      if (is_string($this->meths[$method]))
      { // A string is assumed to be the name of a library.
        // We don't support plain function callbacks.
        $libname  = $this->meths[$method];
        $libobj   = $this->lib[$libname];
        $callback = array($libobj, $method);
      }
      else
      { // Anything else is considered the callback itself.
        // Which can be a class method call, object method call or closure.
        $callback = $this->meths[$method];
      }
      // Now let's dispatch to the callback.
      return call_user_func_array($callback, $arguments);
    }
    // Next we check for hooks.
    if ($this->call_hooks && isset($this->hooks[$method]))
    {
      return $this->callHook($method, $arguments);
    }
    // Next we check for loaders.
    if ($this->call_load && strpos($method, 'load') === 0)
    {
      $loader = strtolower(preg_replace('/load_?/', '', $method));
      if (isset($this->lib[$loader]) 
        && is_callable(array($this->lib[$loader], 'load')))
      {
        return call_user_func_array
        (
          array($this->lib[$loader], 'load'),
          $arguments
        );
      }
    }
    // If we reached this far, we didn't find any methods.
    throw new Exception("Unhandled method '$method' called.");
  }

}

// End of library.

