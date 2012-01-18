<?php

// Adds Models to Nano.

global $__nano_modeldir;
global $__nano_modeltype;

$__nano_modeldir  = 'lib/models'; // Default root folder for models.
$__nano_modeltype = 'model';      // Default suffix for model classes.

// Get the identifier of a model.
function get_model_id ($model)
{ global $__nano_modeltype;
  return get_class_identifier($__nano_modeltype, $model);
}

// Load a model. Feel free to override this function.
// The default is to store models in a 'models' directory,
// and to have a _model suffix on the class name.
// So, load_model('example') will look for a file called
// lib/models/example.php and a class called example_model.
// Remember class names in PHP are not case sensitive, so
// for cosmetic reasons you should use "class Example_Model".
function load_model ($model, $opts=array())
{ global $__nano_modeldir;
  global $__nano_modeltype;
  return load_class
  ( array
    ( 'dir'  => $__nano_modeldir,
      'type' => $__nano_modeltype,
    ), 
    $model, 
    $opts
  );
}

## End of library.
