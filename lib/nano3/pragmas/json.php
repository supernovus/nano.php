<?php

/**
 * The output content will be JSON.
 */

// A horrible hack, make future versions of IE better please?
if (\Nano3\Utils\Browser::is_ie())
{
  header('Content-Type: text/plain');
}
else
{
  header('Content-Type: application/json');
}

