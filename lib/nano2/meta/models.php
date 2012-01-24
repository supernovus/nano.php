<?php

/* Adds a 'models' loader to Nano.
   Models must be in 'lib/models'.
   Model class name must end in '_model' (case insensitive.)
 */

// A function to look up a model's id.
function get_model_id ($model)
{
  $nano = get_nano_instance();
  return $nano->lib['models']->id($model);
}

// Register a 'model' loader in Nano.
$nano = get_nano_instance();
$nano->addClass
(
 'models', 
 'lib/models',
 'model'
);

// End of meta library.

