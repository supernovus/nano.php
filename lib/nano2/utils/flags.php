<?php

/* Handles binary flags, like UNIX permissions. */

// Does a set of flags contain a certain flag?
function check_flag ($flags, $flag)
{
  if ($flags & $flag)
    return true;
  return false;
}

// Add or remove a flag to a given set of flags.
function set_flag (&$flags, $flag, $value=true)
{
  if ($value)
    $flags = $flags | $flag;
  else
    $flags = $flags - ($flags & $flag);
}

// End of library.
