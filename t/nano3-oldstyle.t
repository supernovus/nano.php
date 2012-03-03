<?php

require_once 'lib/nano3/init.php';
require_once 'lib/test.php';

plan(1);

$nano = \Nano3\get_instance();
$nano->extend('oldstyle');
$nano->dispatch->addDefaultController('example4');

$output = $nano->dispatch->dispatch();

is($output, "Hello from example4", "Output from Nano3 'oldstyle' dispatch()");

