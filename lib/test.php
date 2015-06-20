<?php

// An extremely minimal TAP-based testing framework.
// Please use http://code.google.com/p/test-more-php/ for
// serious testing.

global $__test_cur;
global $__test_plan;
global $__test_fail;
$__test_cur = 1;
$__test_plan = 0;
$__test_fail = 0;

function plan ($num)
{ global $__test_plan;
  $__test_plan = $num;
  echo "1..$num\n";
}

function __test ($desc, $test)
{ global $__test_cur;
  global $__test_fail;
  if ($test)
  {
    echo "ok ";
  }
  else
  {
    echo "not ok ";
    $__test_fail++;
  }

  echo $__test_cur;

  if (isset($desc))
    echo " - $desc";

  echo "\n";

  $__test_cur++;
}

function ok ($stmt, $desc=Null)
{
  __test($desc, $stmt);
}

function is ($got, $want, $desc=Null)
{
  $test = ($got === $want);
  __test($desc, $test);
  if (!$test)
  {
    echo "#       got: $got\n";
    echo "#  expected: $want\n";
  }
}

function done_testing ()
{ global $__test_fail;
  global $__test_plan;
  if ($__test_fail)
  {
    echo "# Looks like you failed $__test_fail out of $__test_plan\n";
  }
}

// End of library. I said it was minimal.

