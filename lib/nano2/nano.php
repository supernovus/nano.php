<?php

/* Nano2 Framework Engine
   A modular, extendible, object-oriented engine for building
   PHP web applications using a Model-View-Controller paradigm.
   Unlike Nano 1, this is based on a small object (Nano) which you
   can either create an instance of, or use as a base class in your own
   application. It comes with several helpers. Also unlike Nano 1, there
   are no "eval" calls used in here.
   NOTE: Due to how it's designed, you can only have one Nano instance
   in your application. Any attempt to create a second instance will
   result in failure.
 */

if (!defined('NANODIR'))
{
  define('NANODIR', 'lib/nano2'); // Our default value.
}

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
class NanoException extends Exception {}

// The following can be used to find the Nano object.
global $__nano__instance;
function get_nano_instance ()
{
  global $__nano__instance;
  if (isset($__nano__instance))
  {
    return $__nano__instance;
  }
  else
  {
    return new Nano();
  }
}

/* The base class for Nano loaders. 
   Extend this as needed. The default loads the library and nothing more.
   Useful for helper engines such as Nano extensions.
 */
class NanoLoader
{
  public $dir;               // The directory which contains our classes.
  public $ext = '.php';      // The file extension for classes (.php)
  public function __construct($opts)
  {
    if (isset($opts) && is_array($opts))
    { // A sanity check
      if (!isset($opts['dir']))
        throw new NanoException('Loaders require a dir setting.');
      // Okay, now let's set any found options.
      foreach ($opts as $opt=>$val)
      {
        if (property_exists($this, $opt))
          $this->$opt = $val;
      }
    }
    else
      throw new NanoException('Invalid opts passed to NanoLoader contructor.');
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
    if (file_exists($file))
      require_once $this->filename($class);
    else
      throw new NanoException("Attempt to load invalid class: $file");
  }
}

/* The base class for loading views.
   Extend this as needed, or just make multiple loaders using
   this class if you have multiple types of views (layouts versus screens, etc.)
 */
class NanoViewLoader extends NanoLoader
{
  public function load ($class, $data=NULL)
  {
    $file = $this->file($class);
    if (file_exists($file))
    {
      $output = get_php_content($file, $data);
      return $output;
    }
    else
      throw new NanoException("Attempt to load invalid view: $file");
  }
}

/* The base class for loading object-based classes.
   Useful for controllers, models, etc. Exten as needed.
 */
class NanoClassLoader extends NanoLoader
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
      throw new NanoException("No such class: $classname");
  }

  // Get the identifier of an object, strips off the _$type suffix.
  public function id ($object)
  {
    return get_class_identifier('_'.$this->type, $object);
  }

}

/* The base class for the Nano framework.
   Either make an instance of this directly, or make your own base
   class that extends this. As per the notice above, you cannot have
   more than one instance of a Nano-derived object at one time.
 */

class Nano implements ArrayAccess
{
  public $lib = array();   // An associative array of library objects.

  // Add a library object to Nano. NOTE: The class must already be
  // available, otherwise things will go boom.
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

  // Add a basic NanoLoader library.
  public function addLoader ($name, $dir, $opts=array())
  {
    $opts['dir'] = $dir;
    $this->addLib($name, 'NanoLoader', $opts);
  }

  // Add a NanoClassLoader library.
  public function addClass ($name, $dir, $type, $opts=array())
  {
    $opts['dir'] = $dir;
    $opts['type'] = $type;
    $this->addLib($name, 'NanoClassLoader', $opts);
  }

  // Add a NanoViewLoader library.
  public function addViews ($name, $dir, $opts=array())
  {
    $opts['dir'] = $dir;
    $this->addLib($name, 'NanoViewLoader', $opts);
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
      throw new NanoException('Nano instance already created.');

    // See if we've overridden the nano library folder.
    if (isset($opts['nanodir']))
      $nano_dir = $opts['nanodir'];
    else
      $nano_dir = NANODIR;  // Defined above.

    // Now register this as the 'nano' loader.
    $this->addLoader('nano', $nano_dir);

    // Set the global variable so we can find this later.
    $__nano__instance = $this;

  }

  // Load a meta library (in the 'meta' subfolder of NANODIR)
  // Meta libraries add functionality to Nano itself.
  public function loadMeta ($name)
  {
    $this->lib['nano']->load("meta/$name");
  }

  // Load a base library (in the 'base' subfolder of NANODIR)
  // Base libraries provide some simple base classes for use in your
  // web applications.
  public function loadBase ($name)
  {
    $this->lib['nano']->load("base/$name");
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
    return isset($this->lib[$offset]) ? $this->lib[$offset] : Null;
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

  /* The __call() method makes things like loadController() work. */
  public function __call ($method, $arguments)
  {
    // First, let's check for load methods.
    if (strpos($method, "load") === 0)
    { 
      $loader = strtolower(preg_replace('/load_?/', '', $method));
      $loaders = $loader . 's'; ## Try with plurals.
      foreach (array($loader, $loaders) as $load)
      {
        if (isset($this->lib[$load]) 
          && is_callable(array($this->lib[$load], 'load')))
          return call_user_func_array
            (array($this->lib[$load], 'load'), $arguments);
      }
      throw new NanoException("Unknown loader '$method' called.");
    }
    else
    {
      // Our method didn't start with "load", let's find the first library
      // object that supports the method, and dispatch to it. Cheap, but
      // it works.
      foreach ($this->lib as $lib)
      {
        if (is_callable(array($lib, $method)))
          return call_user_func_array(array($lib, $method), $arguments);
      }
      throw new NanoException("Unhandled method '$method' called.");
    }
  }

}

// End of library.

