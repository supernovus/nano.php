<?php

/**
 * Example application.
 *
 */

// We are using Nano3 as our framework.
require_once 'lib/nano3/init.php';

// Define our pages here.
define('PAGE_DEFAULT', '/');
define('PAGE_LOGIN',   '/login');
define('PAGE_LOGOUT',  '/logout');

// Define our layouts here.
define('LAYOUT_DEFAULT', 'default');

// Create our Nano instance.
$nano = \Nano3\get_instance();

// Load our database configuration.
$nano->conf->loadInto('db', 'conf/db.json', 'json');

// Explicit use of the Dispatch extension.
$nano->extend('dispatch');

// Add routes to the auth controller to handle login and logout.
$nano->addRoutes('auth', array('login', 'logout'));

// Set our "root" controller.
$nano->setRoot('welcome');

// Set the controller to handle unknown paths.
$nano->setDefault('invalid');

// Finally, let's dispatch and get the results.
echo $nano->dispatch();

## End of script.
