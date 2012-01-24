<?php

/* Adds a 'models' loader to Nano.
   Models must be in 'lib/models'.
   Model class name must end in '_model' (case insensitive.)
 */

// Register a 'model' loader in Nano.
$nano = get_nano_instance();
$nano->addClass
(
 'models', 
 'lib/models',
 'model'
);

// End of meta library.

