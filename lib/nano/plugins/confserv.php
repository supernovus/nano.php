<?php

/**
 * ConfServ
 *
 * Helps you build services which return information from a
 * Nano Conf registry in specific formats.
 */

namespace Nano\Plugins;
use Nano\Exception;

class ConfServ
{
  // Get a section, as a PHP array/value. Decode any objects.
  public function getPath ($path, $full=False)
  {
    $nano = \Nano\get_instance();
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

  // A simple JSON-based request. If you need anything more complex
  // than this, you'll need to do it yourself.
  public function getJSON ($path, $full=False, $opts=[])
  {
    $send = isset($opts['send_json']) ? $opts['send_json'] : false;
    $data = $this->getPath($path, $full);
    if ($send)
      return $this->send_json($data, $opts);
    else
      return $this->json_ok($data, $opts);
  }

}

