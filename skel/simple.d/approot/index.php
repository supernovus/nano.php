<?php

/**
 * Example application.
 *
 */

namespace Example;

// We are using Nano4 as our framework.
require_once 'lib/nano4/init.php';

// Create our Nano instance.
$nano = \Nano4\initialize();

// Load our database configuration.
$nano->conf->setDir('conf');

// Add our controllers.
$nano->controllers->addNS("\\Example\\Controllers");
$nano->models->addNS("\\Example\\Models");

// Use default view loaders ('screens' and 'layouts')
// By passing true, we use the default 'views/screens', 'views/layouts'
// folders.
$nano->controllers->use_screens(true);

// Add Dispatch methods into Nano itself.
$nano->router = ['extend'=>true];

// Add routes to the auth controller to handle login and logout.
$nano->addRoute(['controller'=>'auth', false, false)
  ->add('login')
  ->add('logout');
// We could add more bits like forgot password here.

// Set our "root" controller.
$nano->addRoute('/', 'welcome');

// Set the controller to handle unknown paths.
$nano->setDefault('invalid');

// Finally, let's dispatch and get the results.
echo $nano->dispatch();

## End of script.
