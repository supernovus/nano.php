<?php

/* Simple functions for managing PHP session data.
 *
 * Consider using the Nano3 'sess' handler instead, as it can abstract
 * out the details of how session data is stored.
 *
 * This is kept for compatibility with existing systems.
 */

function gets ($name)
{
  if (isset($_SESSION[$name]))
    return $_SESSION[$name];
  else
    return null;
}

function puts ($name, $value)
{
  $_SESSION[$name] = $value;
}

function dels ($name)
{
  unset($_SESSION[$name]);
}

// End of library.