<?php
$nano = \Nano\get_instance();
$root = $nano['classroot'];
if (!isset($root))
  $root = 'lib';
$simpledom_libs = ["$root/SimpleDOM.php", "$root/simpledom.php"];
foreach ($simpledom_libs as $simpledom_lib)
{
  if (file_exists($simpledom_lib))
  {
    require_once $simpledom_lib;
    break;
  }
}
