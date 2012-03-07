<?php

/* Nano3 Framework Engine
   A modular, extendible, object-oriented engine for building
   PHP web applications. This is the 3rd generation engine
   and requires PHP 5.3 or higher.
 */

namespace Nano3;

define('CLASS_ROOT_DIR', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.CLASS_ROOT_DIR);
spl_autoload_extensions('.php');
spl_autoload_register();

// Get the output content from a PHP file.
function get_php_content ($__view_file, $__view_data=NULL)
{ // First, start saving the buffer.
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

// Get a class identifier, stripping the 'type' portion.
function get_class_identifier ($type, $object)
{
    $classname = strtolower(get_class($object));
    $identifier = str_replace($type, '', $classname);
    return $identifier;
}

/* The base class for Nano Exceptions */
class Exception extends \Exception {}

// The following can be used to find the Nano object.
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
    { // A sanity check
      if (!isset($opts['dir']))
        throw new Exception('Loaders require a dir setting.');
      // Okay, now let's set any found options.
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
    return $this->dir . '/' . $class . $this->ext;
  }
  // Does the given class exist?
  public function is ($class)
  {
    $file = $this->file($class);
    return file_exists($file);
  }
  // Load the given class. Override this as needed.
  public function load ($class, $data=NULL)
  {
    $file = $this->file($class);
    require_once $file;
  }
}

/* The base class for the Nano framework.
   Either make an instance of this directly, or make your own base
   class that extends this. As per the notice above, you cannot have
   more than one instance of a Nano-derived object at one time.
 */

class Nano3 implements \ArrayAccess
{
  public $lib = array();   // An associative array of library objects.

  // Add a library object to Nano. 
  public function addLib ($name, $class, $opts=NULL)
  {
    if (is_object($class))
      $this->lib[$name] = $class;
    else
    {
      $lib = new $class($opts);
      $this->lib[$name] = $lib;
    }
  }

  // Add a loader.
  public function addLoader ($name, $dir, $opts=array(), $type=Null)
  {
    $opts['dir'] = $dir;
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
  public function addClass ($name, $dir, $type, $opts=array())
  {
    $opts['type'] = $type;
    $this->addLoader($name, $dir, $opts, 'Class');
  }

  // Add a NanoViewLoader library.
  public function addViews ($name, $dir, $opts=array())
  {
    $this->addLoader($name, $dir, $opts, 'View');
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

    // Set the global variable so we can find this later.
    $__nano__instance = $this;

  }

  // Pragmas change the behavior of the current script.
  public function usePragma ($name)
  {
    $this->lib['nano']->load("pragmas/$name");
  }

  // Extensions add features to Nano3 itself.
  // This is used by the extension autoloader (see __get() for details.)
  public function extend ($name)
  {
    $this->lib['nano']->load("extensions/$name");
  }

  // Load a util library (in the 'utils' subfolder of NANODIR)
  // Utils add functions to the global namespace. Simple stuff only.
  public function loadUtil ($name)
  {
    $this->lib['nano']->load("utils/$name");
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
    elseif ($this->lib['nano']->is("extensions/$offset"))
    {
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

}

// End of library.

