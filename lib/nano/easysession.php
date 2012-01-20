<?php

/* Simple gets() and puts() for managing PHP session data. */

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

## End of library.