<?php

/** 
 * This class represents a controller foundation.
 * The controller can have multiple models, and can load
 * templates consisting of a layout, and a screen.
 * The contents of the screen will be made available as the
 * $view_content variable in the layout.
 * You should create a base class to extend this that provides
 * any application-specific common controller methods.
 *
 */

namespace Nano3\Base;

abstract class Controller 
{
  public $models = array(); // Any Models we have loaded.

  protected $data;          // Our data to send to the templates.
  protected $screen;        // Set if needed, otherwise uses $this->name().
  protected $model_opts;    // Options to pass to load_model(), via model().

  protected $layout;        // The default layout to use.
  protected $default_url;   // Where redirect() goes if no URL is specified.
  protected $json_method;   // Method to convert object to JSON.

  /**
   * Hooks
   *
   * Controller.return_content:
   *   Receives a pointer to the content as a string.
   *   Allows you to apply a filter to the content before it
   *   gets returned.
   *
   * Controller.send_json
   *   Gets a copy of the data to be sent/converted to JSON and
   *   allows you to make adjustments to it before any conversion
   *   takes place.
   */

  // Process a screen template with the given data.
  public function process_template ($screen, $data, $layout=NULL)
  { $nano = \Nano3\get_instance();
    if (is_null($layout))
      $layout = $this->layout;
    // Okay, let's get the view screen output.
    $page = $nano->screens->load($screen, $data);
    if (isset($layout))
    { // We're using a layout model.
      // Please ensure your layout has a view_content variable.
      $data['view_content'] = $page;
      $template = $nano->layouts->load($layout, $data);
      $nano->callHook('Controller.return_content', array(&$template));
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      $nano->callHook('Controller.return_content', array(&$page));
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
  { $nano = \Nano3\get_instance();
    $nano->pragma('no-cache');    // Don't cache this.
    header('Content-Type: application/json');
    $nano->callHook('Controller.send_json', array(&$data));
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
        throw new Exception('Unsupported object sent to sendJSON');
    }
    else
    {
      throw new Exception('Unsupported data type sent to sendJSON');
    }
    return $json;
  }

  // Load a data model.
  protected function load_model ($model, $opts=array())
  { $nano = \Nano3\get_instance();
    $opts['parent'] = $this;
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
    $nano = \Nano3\get_instance();
    return $nano->controllers->id($this);
  }

  // Do we have an uploaded file?
  public function has_upload ($fieldname)
  {
    return \Nano3\Utils\File::hasUpload($fieldname);
  }

  // Get an uploaded file. It will return Null if the upload does not exist.
  public function get_upload ($fieldname)
  {
    return \Nano3\Utils\File::getUpload($fieldname);
  }

  /* Wrappers for the URL plugin methods. */

  public function redirect ($url=Null, $opts=array())
  {
    if (is_null($url))
    {
      $url = $this->default_url;
    }
    return \Nano3\Plugins\URL::redirect($url, $opts);
  }

  public function url ($ssl=Null, $port='')
  {
    return \Nano3\Plugins\URL::site_url($ssl, $port);
  }

  public function request_uri ()
  {
    return \Nano3\Plugins\URL::request_uri();
  }

  public function current_url ()
  {
    return \Nano3\Plugins\URL::current_url();
  }

  public function download ($file, $opts=array())
  {
    return \Nano3\Plugins\URL::download($file, $opts);
  }

}

// End of base class.
