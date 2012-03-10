<?php

require_once 'lib/nano3/init.php';
require_once 'lib/test.php';

plan(1);

$nano = \Nano3\get_instance();
$nano->dispatch->addDefaultController('example3');

$output = $nano->dispatch->dispatch();

is($output, "Hello from example3", "Output from Nano3 dispatch()");

