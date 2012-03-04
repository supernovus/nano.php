<?php

/* This class represents a controller foundation.
   The controller can have multiple models, and can load
   templates consisting of a layout, and a screen.
   The contents of the screen will be made available as the
   $view_content variable in the layout.
   You should create a base class to extend this that provides
   any application-specific common controller methods.
 */

$nano = get_nano_instance();
$nano->loadMeta('templates');     // We use layouts and screens.
$nano->loadMeta('models');        // And models.
$nano->loadMeta('controllers');   // Just a sanity check.

abstract class CoreController 
{ public $models = array(); // Any Models we have loaded.

  protected $data;          // Our data to send to the templates.
  protected $screen;        // Set if needed, otherwise uses $this->name().
  protected $model_opts;    // Options to pass to load_model(), via model().

  protected $layout;        // The default layout to use.
  protected $default_url;   // Where redirect() goes if no URL is specified.
  protected $json_method;   // Method to convert object to JSON.

  // Process a screen template with the given data.
  public function process_template ($screen, $data, $layout=NULL)
  { $nano = get_nano_instance();
    if (is_null($layout))
      $layout = $this->layout;
    // Okay, let's get the view screen output.
    $page = $nano->screens->load($screen, $data);
    if (isset($layout))
    { // We're using a layout model.
      // Please ensure your layout has a view_content variable.
      $data['view_content'] = $page;
      $template = $nano->layouts->load($layout, $data);
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      return $page;
    }
  }

  // A wrapper for process_template that is more friendly.
  public function display ($data=null, $screen=null)
  {
    if (isset($data) && is_array($data))
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data += $this->data;
      }
    }
    else
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data = $this->data;
      }
      else
      {
        $data = array();
      }
    }
    if (is_null($screen))
    { if (isset($this->screen))
        $screen = $this->screen;
      else
        $screen = $this->name();
    }
    return $this->process_template($screen, $data);
  }

  // Sometimes we want to send JSON data instead of a template.
  public function sendJSON ($data)
  { $nano =  get_nano_instance();
    $nano->loadMeta('no-cache');    // Don't cache this.
    header('Content-Type: application/json');
    if (is_array($data)) 
    { // Basic usage is to send simple arrays.
      $json = json_encode($data);
    }
    elseif (is_string($data))
    { // A passthrough for JSON strings.
      $json = $data;
    }
    elseif (is_object($data) && isset($this->json_method))
    { // Magic for converting objects to JSON.
      $method = $this->json_method;
      if (is_callable(array($data, $method)))
        $json = $data->$method();
      elseif (is_callable(array($this, $method)))
        $json = $this->$method($data);
      else
        throw new NanoException('Unsupported object sent to sendJSON');
    }
    else
    {
      throw new NanoException('Unsupported data type sent to sendJSON');
    }
    return $json;
  }

  // Load a data model.
  protected function load_model ($model, $opts=array())
  { $nano = get_nano_instance();
    $this->models[$model] = $nano->models->load($model, $opts);
  }

  // A wrapper for load_model with caching and more options.
  public function model ($model, $opts=array())
  {
    if (!isset($this->models[$model]))
    { // No model has been loaded yet.
      if (isset($this->model_opts) && is_array($this->model_opts))
      { // We have model options in the controller.
        $found_options = false;
        if (isset($this->model_opts['common']))
        { // Common options used by all models.
          $opts += $this->model_opts['common'];
          $found_options = true;
        }
        if (isset($this->model_opts[$model]))
        { // There is model-specific options.
          $opts += $this->model_opts[$model];
          $found_options = true;
        }
        if (!$found_options)
        { // No model-specific or common options found.
          $opts += $this->model_opts;
        }
      }
      $this->load_model($model, $opts);
    }
    return $this->models[$model];
  }

  // Return our controller name.
  public function name ()
  {
    $nano = get_nano_instance();
    return $nano->controllers->id($this);
  }

  // Redirect to another page. This ends the current PHP process.
  public function redirect ($url=null, $opts=array())
  { // Check for some options. 'relative'=>False or 'full'=>True are the same.
    if (isset($opts['relative']))
      $relative = $opts['relative'];
    elseif (isset($opts['full']) && $opts['full'])
      $relative = False;
    else
      $relative = True; // Assume true by default.
    if (isset($opts['secure']))
      $ssl = $opts['secure'];
    elseif (isset($opts['ssl']))
      $ssl = $opts['ssl'];
    else
      $ssl = Null; // Auto determine the protocol.
    if (isset($opts['port']))
      $port = $opts['port'];
    else
      $port = ''; // Use the default ports.

    if (is_null($url))
      $url = $this->default_url;

    if ($relative)
    {
      $url = $this->url($ssl, $port) . $url;
    }

    header("Location: $url");
    exit;
  }

  // Return our base URL.
  public function url ($ssl=Null, $port='')
  { if (isset($ssl))
    { // We're using explicit SSL settings.
      if ($ssl)
      {
        $defport = 443;
        $proto   = "https";
      }
      else
      {
        $defport = 80;
        $proto   = "http";
      }
    }
    else
    { // Auto-detect SSL and port settings.
      if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on")
      { 
        $defport = 443;
        $proto   = "https";
      }
      else
      { 
        $defport = 80;
        $proto   = "http";
      }
      $port = ($_SERVER["SERVER_PORT"] == $defport) ? '' : 
        (":".$_SERVER["SERVER_PORT"]);
    }
    return $proto."://".$_SERVER['SERVER_NAME'].$port;
  }

  // Return our current request URI.
  public function request_uri ()
  {
    if (isset($_SERVER['REQUEST_URI']))
    {
      return $_SERVER['REQUEST_URI'];
    }
    else
    {
      $uri = $_SERVER['SCRIPT_NAME'];
      if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] != '')
      {
        $uri .= '/' . $_SERVER['PATH_INFO'];
      }
      if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '')
      {
        $uri .= '?' . $_SERVER['QUERY_STRING'];
      }
      $uri = '/' . ltrim($uri, '/');

      return $uri;
    }
  }

  // Return the current URL (full URL path)
  public function current_url ()
  {
    $full_url = $this->url() . $this->request_uri();
    return $full_url;
  }

}

// End of base class.
