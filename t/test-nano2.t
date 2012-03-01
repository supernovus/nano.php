<?php

require_once 'lib/nano2/nano.php';
require_once 'lib/test.php';

plan(1);

$nano = get_nano_instance();
$nano->loadMeta('dispatch');
$nano->dispatch->addDefaultController('example2');

$output = $nano->dispatch->dispatch();

is($output, "Hello from example2", "Output from Nano2 dispatch()");

