<?php

/* Adds a 'controllers' loader to Nano.
   Controllers must be in 'lib/controllers'.
   Controller class name must end in '_controller' (case insensitive.)
 */

// Register a 'controller' loader in Nano.
$nano = get_nano_instance();
$nano->addClass
(
 'controllers',
 'lib/controllers',
 'controller'
);

// End of meta library.
