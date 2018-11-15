<?php

namespace Nano\Plugins;

/**
 * Helper class for debugging.
 */
class Debug
{
  /**
   * Perform a stack trace.
   *
   * @param bool $log  If true, output to the error log (default is true.)
   *
   * @return array    The stack trace object.
   */
  public static function trace ($log=true, $limit=0)
  {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
    if ($log)
    {
      error_log(json_encode($trace, JSON_PRETTY_PRINT));
    }
    return $trace;
  }

  /**
   * Look for, and if found, load a debugging config file.
   * The format of the file is simple:
   * 
   *  flag1=1,flag2=3
   *  flag3=true,flag4=false
   *
   * Each of the flags lwill be set as $nano["debug.flagname"]
   * for lookup manually, or via $nano->debug->is().
   *
   * @param string $configfile  The config file to look for and load.
   */
  public static function loadConfig ($configfile)
  {
    if (file_exists($configfile))
    { // Load the debugging config file.
      $nano = \Nano\get_instance();
      $nano_debug_conf = trim(file_get_contents($configfile));
      $nano_debug_conf = preg_split("/[\n\,]/", $nano_debug_conf);
      foreach ($nano_debug_conf as $nano_debug_spec)
      {
        $nano_debug_spec = explode('=', trim($nano_debug_spec));
        $nano_debug_key = trim($nano_debug_spec[0]);
        $nano_debug_val = trim($nano_debug_spec[1]);
        if ($nano_debug_val === 'true')
          $nano_debug_val = true;
        elseif ($nano_debug_val === 'false')
          $nano_debug_val = false;
        else
          $nano_debug_val = intval($nano_debug_val);
        $nano["debug.$nano_debug_key"] = $nano_debug_val;
      }
    }  
  }

  public static function get ($flag, $default=null)
  {
    $nano = \Nano\get_instance();
    $debugval = $nano["debug.$flag"];
    if (isset($debugval))
    {
      return $debugval;
    }
    else
    {
      return $default;
    }
  }

  /**
   * Is a Nano debugging flag set?
   *
   * @param string $flag  The flag we're checking for.
   * @param (bool|int) $checkvalue  Optional value to check for.
   *                                If boolean, the value must match it.
   *                                If integer, the value must be >= to it.
   *                                If not specified, attempts auto-detection.
   *
   * @return bool   Will be true if the flag is set and matches the checkvalue.
   */
  public static function is ($flag, $checkvalue=null)
  {
    $nano = \Nano\get_instance();
    $debugvalue = $nano["debug.$flag"];
    if (isset($debugvalue))
    {
      if (is_bool($checkvalue) && is_bool($debugvalue))
      { // Both checkvalue and debugvalue are boolean.
        return ($checkvalue === $debugvalue);
      }
      elseif (is_numeric($checkvalue) && is_numeric($debugvalue))
      { // Both checkvalue and debugvalue are numeric.
        return ($debugvalue >= $checkvalue);
      }
      elseif (isset($checkvalue))
      { // There's a checkvalue, but it's not the same data type.
        if ($checkvalue && $debugvalue)
        {
          return true;
        }
        elseif (!$checkvalue && !$debugvalue)
        {
          return true;
        }
      }
      else
      { // No checkvalue, use default settings.
        if (is_bool($debugvalue))
        {
          return $debugvalue;
        }
        elseif (is_numeric($debugvalue))
        {
          return ($debugvalue > 0);
        }
      }
    }
    return false;
  }

  /**
   * Parse an Exception, and return a log entry.
   *
   * @param Exception $exception  The exception object.
   * @param bool      $errorlog   Should we output to the error log?
   *
   * @return array  The log array, for further processing.
   */
  public static function parseException ($exception, $errorlog=false)
  {
    $nano = \Nano\get_instance();
    $classname = get_class($exception);
    $log =
    [
      'classname' => $classname,
      'message' => $exception->getMessage(),
      'code'    => $exception->getCode(),
      'file'    => $exception->getFile(),
      'line'    => $exception->getLine(),
    ];
    if ($nano->debug->is('exception'))
    { // Add a stack trace to the exception log.
      $trace = $exception->getTrace();
      foreach ($trace as &$stackitem)
      { // Remove the 'args' property.
        unset($stackitem['args']);
      }
      $log['trace'] = $trace;
    }
    if ($errorlog)
    {
      error_log("Exception occurred: ".json_encode($log, JSON_PRETTY_PRINT));
    }
    return $log;
  }
}