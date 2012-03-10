<?php

/* 
 * Nano2 style controllers and models.
 *
 * Note: this loads 'controllers' and 'models' and sets them up with
 * the _Controller and _Model suffixes, as Nano2 did.
 *
 */

$nano = \Nano3\get_instance();

$nano->addClass
(
  'controllers',
  'lib/controllers',
  '%s_controller',
  array('is_default'=>True)
);

$nano->addClass
(
  'models',
  'lib/models',
  '%s_model',
  array('is_default'=>True)
);

// End of meta library.