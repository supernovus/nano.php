<?php
/* Nano: Smaller than a micro-framework
   
   This is a simple PHP toolkit for building micro-frameworks.
   It's a modular toolkit, allowing specific features to be loaded
   using the load_core() function. This is the main PHP file, and the only
   one that needs to be called using native PHP methods. All the rest can
   be loaded using load_core() and related loader methods.

   NOTE: This is Nano v1, and is deprecated and no longer recommended.
   Please look at upgrading to Nano v2, which is an object-oriented
   version that is far cleaner and does not polute your namespace with
   tons of functions and global variables.

*/

// The only loader we have by default is load_core() which loads extensions
// for Nano itself. The load_base() and load_lib() loaders are now found in
// the 'applibs' extension. Load it if your code depends on those functions.
global $__nano_core_dir;
$__nano_core_dir = 'lib/nano';
make_loader('core');

// Build a function for loading libraries from a specific folder.
// You need to specify the folder as a $__nano_TYPE_dir where TYPE is
// the type of library you are loading. The generated function will be
// called load_TYPE(). Eg. make_loader('core') will generate a function
// called load_core() that looks for a $__nano_core_dir global variable.
function make_loader ($type)
{
  $loader = "function load_$type (\$lib) {
    global \$__nano_${type}_dir;
    require_once \"\$__nano_${type}_dir/\$lib.php\"; 
  }";
#  error_log("new function: $loader");
  eval($loader);
}

// Get the identifier of a class.
// The identifier is in all lowercase,
// with any namespace postfix stripped off.
function get_class_identifier ($type, $class)
{
  $classname = strtolower(get_class($class));
  $identifier = str_replace("_$type", '', $classname);
  return $identifier;
}

// Loads a class. By default it also creates an instance
// of the class, and returns it. You can disable that
// by passing false as the last parameter. Otherwise, you
// can pass options to the constructor by passing an array.
function load_class ($type, $class, $opts=array())
{ if (is_array($type))
  { // Find the name for our directory.
    $dir = $type['dir'];
    // And the name of our class suffix.
    $suffix = $type['type'];
  }
  else
  { // Assume the directory, and type are the same.
    $dir    = $type;
    $suffix = $type;
  }
  //error_log("Dir: '$dir', Suffix: '$suffix', Class: '$class'");
  // First, load the class.
  require_once "$dir/$class.php";
  // Next, let's see if we should create an instance.
  if (is_bool($opts))
  { if ($opts) { $opts = array(); } // The same as passing nothing.
    else { return; }                // Continue no further.
  }
  // If we made it this far, let's initialize the object.
  $classname = $class.'_'.$suffix; // PHP is not case sensitive.
  return new $classname ($opts);
}

## End of library.
