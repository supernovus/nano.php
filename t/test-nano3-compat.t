<?php

require_once 'lib/nano3/init.php';
require_once 'lib/test.php';

plan(1);

$nano = \Nano3\get_instance();
$nano->extend('nano2');
$nano->dispatch->addDefaultController('example2');

$output = $nano->dispatch->dispatch();

is($output, "Hello from example2", 
  "Output from Nano3 dispatch() with Nano2 compatibility.");

