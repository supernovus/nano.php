<?php

/**
 * A simple TAP based testing library.
 *
 * @package test.php
 */

global $__test_cur;
global $__test_plan;
global $__test_fail;
$__test_cur = 1;
$__test_plan = 0;
$__test_fail = 0;

/**
 * Set how many tests we are planning to run.
 *
 * @param int $num The number of tests planned.
 */
function plan ($num)
{ global $__test_plan;
  $__test_plan = $num;
  echo "1..$num\n";
}

/**
 * An internal function used by all of the other functions.
 *
 * @param string $desc The name of the test.
 * @param boolean $test The result of the test.
 */
function __test ($desc, $test, $directive=null)
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

  if (isset($directive))
    echo " # $directive";

  echo "\n";

  $__test_cur++;
}

/**
 * See if a statement is true or false.
 *
 * @param boolean $stmt The statement to test.
 * @param string $desc A description of the test (optional.)
 */
function ok ($stmt, $desc=Null)
{
  __test($desc, $stmt);
}

/**
 * Is a test value exactly equal to an expected value?
 *
 * @param mixed $got The value being tested.
 * @param mixed $want The expected value.
 * @param string $desc A description for the test (optional.)
 */
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

/**
 * Skip a test
 */
function skip ($reason, $desc=null)
{
  __test($desc, true, "SKIP $reason");
}

/**
 * Show a diagnostic/debugging message as a TAP comment.
 *
 * @param string $msg The message to show.
 */
function diag ($msg)
{
  $lines = explode("\n", $msg);
  foreach ($lines as $line)
    echo "# $line\n";
}

/**
 * Signify that we are finished testing.
 */
function done_testing ()
{ global $__test_fail;
  global $__test_plan;
  if ($__test_fail)
  {
    echo "# Looks like you failed $__test_fail out of $__test_plan\n";
  }
}

// End of library. I said it was minimal.

