<?php 

require_once "lib/nano/init.php";
require_once "lib/test.php";

plan(2);

\Nano\register();

$src1 = 
[
  ['male','female','other'],
  ['< 19', '19-25', '25-50', '> 50'],
];

$want1 = '[["male","< 19"],["male","19-25"],["male","25-50"],["male","> 50"],["female","< 19"],["female","19-25"],["female","25-50"],["female","> 50"],["other","< 19"],["other","19-25"],["other","25-50"],["other","> 50"]]';

$res1 = json_encode(Nano\Utils\Arry::cartesian_product($src1));

is($res1, $want1);

$src2 =
[
  ['male','female']
];

$res2 = json_encode(Nano\Utils\Arry::cartesian_product($src2));

$want2 = '[["male"],["female"]]';

is($res2, $want2);

