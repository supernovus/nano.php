<?php

// Adds Views to Nano.

global $__nano_viewdir;
$__nano_viewdir  = 'views';      // Default root folder for views.

// Get the output content from a PHP file.
// This is used by load_view(), but is separated
// in case you want to override the load_view function.
function get_php_content ($__view_dir, $__view_file, $__view_data=NULL)
{ // First, start saving the buffer.
  ob_start();
  if (isset($__view_data) && is_array($__view_data))
  { // If we have data, add it to the local namespace.
    extract($__view_data);
  }
  // Now, let's load that template file.
  include "$__view_dir/$__view_file.php";
  // Okay, now let's get the contents of the buffer.
  $buffer = ob_get_contents();
  // Clean out the buffer.
  @ob_end_clean();
  // And return out processed view.
  return $buffer;
}

// Load a View. We use PHP scripts as views. They will get an environment
// containing the variables passed to the load_view()'s $data option.
// This default version looks for a directory called 'views'.
function load_view ($view, $data=NULL)
{ global $__nano_viewdir;
  return get_php_content($__nano_viewdir, $view, $data);
}

## End of library.
