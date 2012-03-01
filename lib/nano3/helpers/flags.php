<?php

// Add or remove a flag to a given binary mask.
function set_flag (&$flags, $flag, $value=true)
{
  if ($value)
    $flags = $flags | $flag;
  else
    $flags = $flags - ($flags & $flag);
}

// End of library.
