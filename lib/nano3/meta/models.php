<?php

/* Adds a 'models' loader to Nano.
   Models must be in 'lib/models'.
   Model class must be in the "\Models\" namespace.
   (case insensitive.)
 */

// Register a 'model' loader in Nano.
$nano = \Nano3\get_instance();
$nano->addClass
(
 'models', 
 "\\Models\\%s",
 array('is_default'=>true)
);

// End of meta library.

