<?php
$nano = \Nano\get_instance();
$root = $nano['classroot'];
if (!isset($root))
  $root = 'lib';
$autoload_file = "$root/vendor/autoload.php";
if (file_exists($autoload_file))
  require_once $autoload_file;

