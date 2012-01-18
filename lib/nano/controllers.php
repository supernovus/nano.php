<?php
// Add Controllers to Nano.

global $__nano_controller_dir;
global $__nano_controller_type;

$__nano_controller_dir  = 'lib/controllers';
$__nano_controller_type = 'controller';

// Load a controller.
function load_controller ($name, $opts=array())
{ global $__nano_controller_dir;
  global $__nano_controller_type;
  return load_class
  ( array('dir'=>$__nano_controller_dir, 'type'=>$__nano_controller_type),
    $name,
    $opts
  );
}

// Get a controller id.
function get_controller_id ($cont)
{ global $__nano_controller_type;
  return get_class_identifier($__nano_controller_type, $cont);
}

// End of library.
