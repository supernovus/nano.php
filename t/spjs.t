#!/usr/bin/env php
<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

$nano = \Nano\initialize();

plan(12);

$dataset =
[
  ["test1"=>1, "test2"=>1],
  ["test1"=>1, "test2"=>2],
  ["test1"=>1, "test2"=>3],
  ["test1"=>1, "test2"=>4],
  ["test1"=>1, "test2"=>5],
  ["test1"=>1, "test2"=>6],
  ["test1"=>2, "test2"=>1],
  ["test1"=>2, "test2"=>2],
  ["test1"=>2, "test2"=>3],
  ["test1"=>2, "test2"=>4],
  ["test1"=>2, "test2"=>5],
  ["test1"=>2, "test2"=>6],
];

$spjs = new \Nano\Utils\SPJS($dataset);

$statements =
[
  [ // First we set a "test3" based on the other test values.
    [
      "if"  => ["test1"=>1, "test2"=>[1,6]],
      "set" => ["test3"=>"1_first_last"],
    ],
    [
      "if"  => ["test1"=>2, "test2"=>[">"=>1,"<"=>6,"!="=>4]],
      "set" => ["test3"=>"2_middle"],
    ],
    [
      "always" => true,
      "set"    => ["test3"=>"other"],
    ],
  ],
  [ // Next for a couple select groups, set "test4".
    [
      "if"  => ["test1"=>1, "test3"=>"other"],
      "set" => ["test4"=>true]
    ],
    [
      "if"  => ["test1"=>2, "test3"=>"other"],
      "set" => ["test4"=>false]
    ]
  ],
  [
    [ // Skip this test, test6 should never be set.
      "always" => false,
      "set"    => ["test6"=>true],
    ],
    [ // Set test5 to true if test4 is null.
      "if"  => ["test4"=>null],
      "set" => ["test5"=>true]
    ],
    [ // Set test5 to false in any other case.
      "always" => true,
      "set"    => ["test5"=>false]
    ]
  ],
];

// Process the dataset against the statements.
$processed = $spjs->process($statements);

#error_log("processed: ".json_encode($processed, JSON_PRETTY_PRINT));

$tests =
[
  ["test1"=>1, "test2"=>1, "test3"=>"1_first_last",                 "test5"=>true],
  ["test1"=>1, "test2"=>2, "test3"=>"other",        "test4"=>true,  "test5"=>false],
  ["test1"=>1, "test2"=>3, "test3"=>"other",        "test4"=>true,  "test5"=>false],
  ["test1"=>1, "test2"=>4, "test3"=>"other",        "test4"=>true,  "test5"=>false],
  ["test1"=>1, "test2"=>5, "test3"=>"other",        "test4"=>true,  "test5"=>false],
  ["test1"=>1, "test2"=>6, "test3"=>"1_first_last",                 "test5"=>true],
  ["test1"=>2, "test2"=>1, "test3"=>"other",        "test4"=>false, "test5"=>false],
  ["test1"=>2, "test2"=>2, "test3"=>"2_middle",                     "test5"=>true],
  ["test1"=>2, "test2"=>3, "test3"=>"2_middle",                     "test5"=>true],
  ["test1"=>2, "test2"=>4, "test3"=>"other",        "test4"=>false, "test5"=>false],
  ["test1"=>2, "test2"=>5, "test3"=>"2_middle",                     "test5"=>true],
  ["test1"=>2, "test2"=>6, "test3"=>"other",        "test4"=>false, "test5"=>false],
];

foreach ($tests as $i => $test)
{
  $s1 = serialize($test);
  $s2 = serialize($processed[$i]);
  is ($s2, $s1, "row $i processed");
}
