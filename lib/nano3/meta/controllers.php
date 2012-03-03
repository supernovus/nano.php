<?php

/* Adds a 'controllers' loader to Nano.
   Controllers must be in 'lib/controllers'.
   Controller class must be in the \Controllers\ namespace 
   (case insensitive.)
 */

// Register a 'controller' loader in Nano.
$nano = \Nano3\get_instance();
$nano->addClass
(
 'controllers',
 "\\Controllers\\%s",
 array('is_default'=>True)
);

// End of meta library.
