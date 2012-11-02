<?php 

require_once "lib/nano3/init.php";
#require_once "lib/test.php";

#plan(2);

$src1 = 
[
  ['male','female','other'],
  ['< 19', '19-25', '25-50', '> 50'],
];

$res1 = Nano3\Utils\Arry::cartesian_product($src1);

echo json_encode($res1) . "\n\n";

$src2 =
[
  ['male','female']
];

$res2 = Nano3\Utils\Arry::cartesian_product($src2);

echo json_encode($res2);

