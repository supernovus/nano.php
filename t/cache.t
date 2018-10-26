#!/usr/bin/env php
<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

\Nano\register();

class CacheTest
{
  use \Nano\Meta\Cache;
}

$cache = new CacheTest();

$sampledata =
[
  ['b', 123],
  ['t', 321],
  [['z','a'], ["hello"=>"world"]],
  [['z','b'], ["goodbye"=>"universe"]],
  [['f','a','r','t'], 'fart'],
];

plan(count($sampledata)*2);

foreach ($sampledata as $i => $s)
{
  is ($cache->cache($s[0]), null, "sample $i empty at start");
  $cache->cache($s[0], $s[1]);
  is ($cache->cache($s[0]), $s[1], "sample $i has correct value");
}