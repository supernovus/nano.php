<?php

namespace Nano\Plugins;

class Debug
{
  public static function trace ($log=true)
  {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    if ($log)
    {
      error_log(json_encode($trace, JSON_PRETTY_PRINT));
    }
    else
    {
      return $trace;
    }
  }
}