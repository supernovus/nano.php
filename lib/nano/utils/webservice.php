<?php

namespace Nano\Utils;

/**
 * A generic web service library. Uses GuzzleHTTP for requests.
 *
 * For more specific uses, you can make a subclass with common functionality.
 */
class WebService
{
  public $_debug_level = 0;

  protected $client;      // Guzzle client.
  protected $base_uri;    // Base URI for requests.
  protected $servicename; // Optional way of specifying a default service.
  protected $meths = [];  // Method definitions. Override in sub-classes.
  protected $headers = [];// Extra headers to add to every request.

  // We support a built-in 'json' data type by default.
  // Further types may be added by sub-classes.
  protected $data_type = 'json'; 

  public $default_http = 'POST'; // The default HTTP method.

  /**
   * Create a WebService instance.
   *
   * @param array $opts  Options to initialize with.
   *
   * Supported options:
   *
   *  'service'   Get service options from $nano->conf->services[$service].
   *  'url'       The base URL requests are sent to.
   *  'type'      The content type (defaults to 'json'.)
   *
   * If opts are omitted, but we have a $this->servicename set, that will
   * be used as if a 'service' option was passed.
   */
  public function __construct ($opts=[])
  {
    $nano = \Nano\get_instance();
    if (isset($opts['service']))
    {
      if (is_array($opts['service']))
      {
        $service = $opts['service'];
      }
      elseif (is_string($opts['service']))
      {
        $service = $nano->conf->services[$opts['service']];
      }
      else
      {
        throw new \Exception("Invalid service parameter.");
      }
    }
    elseif (isset($this->servicename))
    {
      $service = $nano->conf->services[$this->servicename];
    }
    else
    { // Nothing else found, use the opts directly.
      $service = $opts;
    }

    if (isset($service['type']))
    {
      $this->data_type = $service['type'];
    }
    elseif (isset($opts['type']))
    {
      $this->data_type = $opts['type'];
    }

    if (isset($service['url']))
    {
      $this->base_uri = $service['url'];
    }
    elseif (isset($opts['url']))
    {
      $this->base_uri = $opts['url'];
    }

    if (isset($service['methods']))
    { // Add some methods from the service spec.
      foreach ($service['methods'] as $mname => $mspec)
      {
        $this->meths[$mname] = $mspec;
      }
    }

    if (isset($opts['routing']))
    {
      foreach ($opts['routing'] as $rname => $rvalue)
      {
        $this->base_uri = preg_replace("/:$rname/", $rvalue, $this->base_uri);
      }
    }

    if (method_exists($this, 'setup_service'))
    {
      $this->setup_service($service, $opts);
    }

    if (isset($service['guzzle']))
    {
      $guzopts = $service['guzzle'];
    }
    elseif (isset($opts['guzzle']))
    {
      $guzopts = $opts['guzzle'];
    }
    else
    {
      $guzopts = [];
    }

    if (isset($this->base_uri))
    {
      $guzopts['base_uri'] = $this->base_uri;
    }

    $this->client = new \GuzzleHttp\Client($guzopts);
  }

  protected function build_common_request ($data, $opts)
  {
    $request = [];
    $headers = $this->headers;
    if (isset($opts['headers']))
    {
      foreach ($opts['headers'] as $k => $v)
      {
        $headers[$k] = $v;
      }
    }
    if (count($headers) > 0)
    {
      $request['headers'] = $headers;
    }
    return $request;
  }

  protected function build_json_request ($meth, $data, $opts)
  {
    $request = $this->build_common_request($data, $opts);
    if (is_string($data))
    {
      if (isset($request['headers']))
        $request['headers']['Content-Type'] = 'application/json';
      else
        $request['headers'] = ['Content-Type' => 'application/json'];
      $request['body'] = $data;
    }
    elseif (is_array($data))
    {
      $request['json'] = $data;
    }
    elseif (is_object($data) && is_callable([$data, 'to_array']))
    {
      $request['json'] = $data->to_array();
    }
    else
    {
      throw new \Exception("Invalid JSON data type used in request.");
    }
    return $request;
  }

  protected function build_json_response ($response, $opts)
  {
    $jsonText = (string)$response->getBody();
    if (isset($jsonText))
    {
      $jsonData = json_decode($jsonText, true);
      return $jsonData;
    }
  }

  protected function get_path ($meth, &$data, $opts)
  {
    if (isset($this->meths[$meth]))
    { // Get the path from the method definition.
      $mdef = $this->meths[$meth];
      if (isset($mdef['path']))
      {
        $path = $mdef['path'];
        $replace = function ($matches) use (&$data)
        {
          if (count($matches) > 1)
          {
            $param = $matches[1];
            if (isset($data[$param]))
            {
              $value = $data[$param];
              unset($data[$param]);
              return $value;
            }
            else
            {
              error_log("Missing param '$param'");
              return '';
            }
          }
        };
        $path = preg_replace_callback("/\:([\w-]+)/", $replace, $path);
        return $path;
      }
    }
    // No explicit path? Assume /methodname.
    return '/'.$meth;
  }

  protected function get_http ($meth, $data, $opts)
  {
    if (isset($this->meths[$meth]))
    { // Get the type from the method definition.
      $mdef = $this->meths[$meth];
      if (isset($mdef['http']))
      { // Use the specified HTTP method.
        return $mdef['http'];
      }
    }
    return $this->default_http;
  }

  public function set_header ($key, $val)
  {
    $this->headers[$key] = $val;
  }

  public function del_header ($key)
  {
    unset($this->headers[$key]);
  }

  public function send_request ($meth, $data, $opts=[])
  {
    if (method_exists($this, 'setup_request'))
    {
      $this->setup_request($meth, $data, $opts);
    }
    $path = $this->get_path($meth, $data, $opts);
    $http = $this->get_http($meth, $data, $opts);
    $type = $this->data_type;
    $mname = "build_{$type}_request";
    if (method_exists($this, $mname))
    {
      $request = $this->$mname($meth, $data, $opts);
    }
    else
    {
      throw new \Exception("No request handler for '$type' data type.");
    }

    if ($this->_debug_level > 0)
    {
      $debugData =
      [
        'method'  => $meth,
        'path'    => $path,
        'http'    => $http,
        'type'    => $type,
        'request' => $request,
      ];
      error_log("Sending request: ".json_encode($debugData, JSON_PRETTY_PRINT));
    }

    $response = $this->client->request($http, $path, $request);

    if (isset($opts['rawResponse']) && $opts['rawResponse'])
    {
      return $response;
    }

    $mname = "build_{$type}_response";
    if (method_exists($this, $mname))
    {
      $response = $this->$mname($response, $opts);
    }
    else
    {
      throw new \Exception("No response handler for '$type' data type.");
    }

    if (method_exists($this, 'setup_response'))
    {
      $this->setup_response($response, $opts);
    }

    return $response;
  }

  /**
   * Make a web service method call.
   */
  public function __call ($method, $arguments)
  {
    if (isset($arguments[0]))
      $data = $arguments[0]; // Pass the data as the first parameter.
    else
      $data = [];
    return $this->send_request($method, $data);
  }

  /**
   * Set a method definition.
   */
  public function __set ($mname, $mdef)
  {
    $this->meths[$mname] = $mdef;
  }

  /**
   * Get a method definition.
   */
  public function __get ($mname)
  {
    if (isset($this->meths[$mname]))
      return $this->meths[$mname];
  }

  /**
   * Unset a method definition.
   */
  public function __unset ($mname)
  {
    unset($this->meths[$mname]);
  }

  /**
   * Is a method definition set?
   */
  public function __isset ($mname)
  {
    return isset($this->meths[$mname]);
  }

}