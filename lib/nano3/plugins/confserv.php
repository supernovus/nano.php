<?php

/**
 * ConfServ
 *
 * Helps you build services which return information from a
 * Nano3 Conf registry in JSON format.
 *
 */

namespace Nano3\Plugins;
use Nano3\Exception;

class ConfServ
{
  // Get a JSON string representing a section.
  public function getPath ($path, $full=False)
  {
    $nano = \Nano3\get_instance();
    $section = $nano->conf->getPath($path);
    if ($full)
    {
      if (is_object($section))
      {
        if (is_callable(array($section, 'to_json')))
        {
          return $section->to_json();
        }
        elseif (is_callable(array($section, 'to_array')))
        {
          return json_encode($section->to_array(), true);
        }
        else
        {
          return 'null';
        }
      }
      else
      {
        return json_encode($section, true);
      }
    }
    else
    {
      if (is_object($section))
      {
        if (is_callable(array($section, 'array_keys')))
        {
          $keys = $section->array_keys();
        }
        else
        {
          return 'null';
        }
      }
      elseif (is_array($section))
      {
        $keys = array_keys($section);
      }
      elseif (is_string($section) || is_numeric($section))
      {
        return json_encode($section, true);
      }
      return json_encode($keys, true);
    }
  }

  // Process a request. Gets the JSON, and returns it to the client.
  public function request ($path, $full=False)
  {
    $nano = \Nano3\get_instance();
    $nano->pragma('no-cache');
    header('Content-Type: application/json');
    $data = $this->getPath($path, $full);
    echo $data;
    exit;
  }

}
