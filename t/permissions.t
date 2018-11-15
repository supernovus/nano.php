#!/usr/bin/env php
<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

use Nano\Utils\File\Permissions;

\Nano\register();

$ten_tests =
[
  'drwxr-xr-x' => 0755,
  '-rw-r--r--' => 0644,
  '-rwsr-sr--' => 06754,
  '-rwsr-Sr--' => 06744,
  '-rwSr--r--' => 04644,
  '-rw-r--r-T' => 01644,
  '-rwxr-xr-t' => 01755,
];

$mod_tests =
[
  ['u=rwx,g=rx,o=r', 0754, 0, true, 'drwxr-xr--'],
  ['og+w', 0666, 0644, false, '-rw-rw-rw-'],
  ['u=rw,g=u', 0664, 0714, false, '-rw-rw-r--'],
  ['go-x', 0766, 0777, false, '-rwxrw-rw-'],
  ['u+s', 04755, 0755, true, 'drwsr-xr-x'],
  ['u+s,a-x', 04644, 0755, false, '-rwSr--r--'],
  ['o+X', 0701, 0700, false, '-rwx-----x'],
  ['g+X', 04710, 04700, false, '-rws--x---'],
  ['u+X', 0644, 0644, false, '-rw-r--r--'],
  ['+r', 0444, 0, false, '-r--r--r--'],
  ['-x', 0644, 0755, false, '-rw-r--r--'],
];

$testCount = (count($ten_tests)*2)+(count($mod_tests)*2);

plan($testCount);

// First do all the 10 character tests.
foreach ($ten_tests as $string => $wantmode)
{
  $isDir = ($string[0] == 'd');
  is(Permissions::parse($string), $wantmode, "parsed '$string'");
  is(Permissions::encode($wantmode, $isDir), $string, "encoded '".decoct($wantmode)."'");
}

// Next do the mod_tests.
foreach ($mod_tests as $mod_test)
{
  list($string, $wantmode, $startmode, $isdir, $wantstr) = $mod_test;
  is(Permissions::convert_mod($string, $startmode, $isdir), $wantstr, "converted '$string'");
  is(Permissions::parse($string, $startmode), $wantmode, "parsed '$string'");
}

