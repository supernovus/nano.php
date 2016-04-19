<?php
/**
 * Example
 *
 * An application skeleton template for Nano4.php
 */

namespace Example;

// Our base settings for the directory structure this application is using.
$nano_opts =
[
  'classroot'  => '../lib',     // Folder where our libraries are.
  'viewroot'   => '../views',   // Folder where our views are.
  'confroot'   => '../conf',    // Folder where our configuration is.
];

// Load the bootstrap library. It will do the rest of the magic.
require_once $nano_opts['classroot'] . '/example/bootstrap.php';

// Bootstrap the application.
$router = Bootstrap::all($nano_opts);

// Route to the appropriate controller via the current URL.
echo $router->route();
