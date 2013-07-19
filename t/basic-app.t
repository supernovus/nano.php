<?php

require_once 'lib/nano4/init.php';
require_once 'lib/test.php';

plan(1);

# TODO: more tests.

$nano = \Nano4\initialize([]);
$nano->controllers->addNS("\\Controllers");
$nano->dispatch->addDefaultController('example');

$output = $nano->dispatch();

is($output, "Hello from example", "Output from Nano dispatch()");

