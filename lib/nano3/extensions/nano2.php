<?php

/* 
 * Nano2 style controllers and models.
 *
 * Note: this loads 'controllers' and 'models' and sets them up with
 * the _Controller and _Model suffixes, as Nano2 did.
 *
 * It also adds the get_nano_instance() method.
 */

function get_nano_instance ()
{
  return \Nano3\get_instance();
}

$nano = get_nano_instance();

// Compatibility with the old loadMeta method.
$nano->addMethod('loadMeta', function ($n, $meta)
{
  $loader = $n->lib['nano'];
  if ($loader->is("extensions/$meta"))
  {
    $loader->load("extensions/$meta");
  }
  elseif ($loader->is("pragmas/$meta"))
  {
    $loader->load("pragmas/$meta");
  }
  else
  {
    throw new Exception("Invalid meta library: '$meta'.");
  }
});

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