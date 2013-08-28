<?php

/**
 * AJAX+JSON Service for Session State Storage.
 * Symbolic link into your project folder.
 * This expects Nano4 to be in the 'lib/nano4/' folder.
 */

// Boot up Nano.
require_once 'lib/nano4/init.php';

// Get our Nano instance.
$nano = \Nano4\get_instance();

// We're returning JSON, and don't want to cache the results.
$nano->pragmas['json no-cache'];

if (isset($nano->sess->state_store))
{
  $state = $nano->sess->state_store;
}
else
{
  $state = array();
}

$return = array();
$changed = False;

foreach ($_REQUEST as $rkey => $rval)
{
  if (strpos($rkey, 'get_')===0)
  {
    $key = str_replace('get_', '', $rkey);
    if (isset($state[$key]))
    {
      $return[$key] = $state[$key];
    }
    else
    {
      $return[$key] = $rval;
    }
  }
  elseif (strpos($rkey, 'set_')===0)
  {
    $key = str_replace('set_', '', $rkey);
    $state[$key] = $rval;
    $changed = True;
  }
}

if ($changed)
{
  $nano->sess->state_store = $state;
}

echo json_encode($return);
exit;

