<?php

// Let's make dispatch more useful.

$nano = \Nano3\get_instance();       // Get our Nano3 instance.
$nano->addPlugin('dispatch');        // Load the Dispatch plugin.

$dispatch = $nano->lib['dispatch'];  // Get the dispatch object.

// Now let's add some method wrappers to Nano3 itself.
// First, some simple ones that do a one-to-one mapping.
$nano->addMethod('dispatch',   'dispatch');    
$nano->addMethod('addRoute',   'dispatch');
$nano->addMethod('addRoutes',  'dispatch');
// Now some more complex ones with new names.
$nano->addMethod('setDefault', array($dispatch, 'addRootController'));
$nano->addMethod('setRoot',    array($dispatch, 'addRootController'));

// That's all folks, for the rest, use dispatch directly.
