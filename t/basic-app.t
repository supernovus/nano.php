<?php

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

plan(2);

# TODO: more tests.

$nano = \Nano\initialize();
$nano->controllers->addNS("\\TestApp\\Controllers");
$nano->router->setDefault('example');

$output = $nano->router->route();

is($output, "Hello from example.", "Output from controller.");

$_REQUEST['name'] = 'Bob';

$output = $nano->router->route();

is($output, "Hello from example, how are you Bob?", "Output with param.");

