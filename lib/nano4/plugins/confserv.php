<?php

/**
 * ConfServ
 *
 * Helps you build services which return information from a
 * Nano4 Conf registry in specific formats.
 *
 * We currently have JSON output as our natively supported format.
 *
 */

namespace Nano4\Plugins;
use Nano4\Exception;

class ConfServ
{
  // Get a section, as a PHP array/value. Decode any objects.
  public function getPath ($path, $full=False)
  {
    $nano = \Nano4\get_instance();
    $section = $nano->conf->getPath($path);
    if ($full)
    {
      if (is_object($section))
      {
        if (is_callable([$section, 'to_array']))
        {
          return $section->to_array();
        }
        else
        {
          return Null;
        }
      }
      else
      {
        return $section;
      }
    }
    else
    { // Get a summary, consisting of the keys.
      $keys = Null;
      if (is_object($section))
      {
        if (is_callable([$section, 'scanDir']))
        { // Pre-load all known entities.
          $section->scanDir();
        }
        if (is_callable([$section, 'array_keys']))
        {
          $keys = $section->array_keys();
        }
        else
        {
          return Null;
        }
      }
      elseif (is_array($section))
      {
        $keys = array_keys($section);
      }
      elseif (is_string($section) || is_numeric($section))
      {
        return $section;
      }
      return $keys;
    }
  }

  // Process a request. PHP value to encode.
  // To send a pre-encoded value, pass 'encoded'=>True as an option.
  public function sendJSON ($data, $opts=[])
  {
    $nano = \Nano4\get_instance();
    $nano->pragmas['json no-cache'];
    if (isset($opts['encoded']) && $opts['encoded'])
    {
      echo $data;
    }
    else
    {
      if (is_object($data) && is_callable([$data, 'to_json']))
      {
        echo $data->to_json($opts);
      }
      else
      {
        $json_opts = 0;
        if (isset($opts['fancy']) && $opts['fancy'])
          $json_opts = JSON_PRETTY_PRINT;

        echo json_encode($data, $json_opts);
      }
    }
    exit;
  }

  // A simple JSON-based request. If you need anything more complex
  // than this, you'll need to do it yourself.
  public function jsonRequest ($path, $full=False, $fancy=False)
  {
    $data = $this->getPath($path, $full);
    $this->sendJSON($data, ['fancy'=>$fancy]);
  }

}

