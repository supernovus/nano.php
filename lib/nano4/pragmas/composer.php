<?php
$nano = \Nano4\get_instance();
$root = $nano['classroot'];
if (!isset($root))
  $root = 'lib';

require_once "$root/vendor/autoload.php";
