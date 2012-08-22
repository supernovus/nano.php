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
 * It now has integration with the nano.js project, and can populate
 * the $scripts template variable with appropriate javascript files.
 *
 * Hooks
 *
 * Controller.return_content:
 *   Receives a pointer to the content as a string.
 *   Allows you to apply a filter to the content before it
 *   gets returned.
 *
 * Controller.send_json
 *   Gets a copy of the data to be sent/converted to JSON and
 *   allows you to make adjustments to it before any conversion.
 *
 * Controller.send_xml
 *   Geta a copy of the data to be sent/converted to XML and
 *   allows you to make adjustments to it before any conversion.
 *
 */

namespace Nano3\Base\Controllers;
use Nano3\Exception;

abstract class Basic 
{
  public $models = array(); // Any Models we have loaded.

  protected $data = array();// Our data to send to the templates.
  protected $screen;        // Set if needed, otherwise uses $this->name().
  protected $model_opts;    // Options to pass to load_model(), via model().

  protected $layout;        // The default layout to use.
  protected $default_url;   // Where redirect() goes if no URL is specified.

  // The method to convert objects to JSON.
  protected $to_json_method = 'to_json';

  // The methood to convert objects to XML.
  protected $to_xml_method  = 'to_xml';

  // Set to true to enable the model cache.
  protected $use_model_cache = False;

  // Override this if you have a different script path.
  // The default assumes you have a a "scripts" folder, and
  // within it, a copy or link to the Nano.js scripts folder called 'nano'.
  protected $script_path = array('scripts', 'scripts/nano');
  // Override this on a global or per-controller basis to set the
  // preferred script extensions. By default we prefer Closure Compiler,
  // followed by minified, followed by raw .js.
  protected $script_exts = array('.cc.js', '.min.js', '.js');
  // A few scripts included with nano.js use the raw .js extension but are
  // in fact minified. To help sort those ones out, we list them here.
  protected $script_opts = array
  (
    'jquery'     => array('file'=>'scripts/nano/jquery.js'),
    'underscore' => array('file'=>'scripts/nano/underscore.js'),
    'less'       => array('file'=>'scripts/nano/less.js'),
  );
  // Groups can be included easily.
  protected $script_groups = array
  ( // A set of common scripts included in the nano.js toolkit.
    '#common' => array('jquery','json2','json.jq','disabled.jq','exists.jq'),
  );
  // Keep track of scripts and groups we've added, and don't duplicate stuff.
  protected $script_added = array();

  /**
   * Display the contents of a screen, typically within a common layout.
   * We use the $data class member as the array of variables to pass to
   * the template.
   */
  public function display ($opts=array())
  {
    // Get Nano.
    $nano = \Nano3\get_instance();

    // Figure out which screen to display.
    if (isset($opts['screen']))
      $screen = $opts['screen'];
    elseif (isset($this->screen))
      $screen = $this->screen;
    else
      $screen = $this->name();

    // Now figure out what we want for a layout.
    if (isset($opts['layout']))
      $layout = $opts['layout'];
    else
      $layout = $this->layout;

    // Make sure the 'parent' is set correctly.
    if (!isset($this->data['parent']))
      $this->data['parent'] = $this;

    // Okay, let's get the screen output.
    // The screen may use the $parent object to modify our data.
    $page = $nano->screens->load($screen, $this->data);

    if ($layout)
    { // Please ensure your layout has a view_content variable.
      $this->data['view_content'] = $page;
      $template = $nano->layouts->load($layout, $this->data);
      $nano->callHook('Controller.return_content', array(&$template));
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      $nano->callHook('Controller.return_content', array(&$page));
      return $page;
    }
  }

  // Sometimes we want to send JSON data instead of a template.
  public function send_json ($data)
  { 
    $nano = \Nano3\get_instance();
    $nano->pragma('json no-cache');    // Don't cache this.
    $nano->callHook('Controller.send_json', array(&$data));
    if (is_array($data)) 
    { // Basic usage is to send simple arrays.
      $json = json_encode($data);
    }
    elseif (is_string($data))
    { // A passthrough for JSON strings.
      $json = $data;
    }
    elseif (is_object($data))
    { // Magic for converting objects to JSON.
      $method = $this->to_json_method;
      if (is_callable(array($data, $method)))
        $json = $data->$method();
      elseif (is_callable(array($this, $method)))
        $json = $this->$method($data);
      else
        throw new Exception('Unsupported object sent to send_json()');
    }
    else
    {
      throw new Exception('Unsupported data type sent to send_json()');
    }
    return $json;
  }

  // Sometimes we want to send XML data instead of a template.
  public function send_xml ($data)
  {
    $nano = \Nano3\get_instance();
    $nano->pragma('xml no-cache');
    $nano->callHook('Controller.send_xml', array(&$data));
    if (is_string($data))
    { // Passthrough.
      $xml = $data;
    }
    elseif (is_object($data))
    {
      $method = $this->to_xml_method;
      if ($data instanceof \SimpleXMLElement)
        $xml = $data->asXML();
      elseif ($data instanceof \DOMDocument)
        $xml = $data->saveXML();
      elseif ($data instanceof \DOMElement)
        $xml = $data->ownerDocument->saveXML();
      elseif (is_callable(array($data, $method)))
        $xml = $data->$method();
      elseif (is_callable(array($this, $method)))
        $xml = $this->$method($data);
      else
        throw new Exception('Unsupported object sent to send_xml()');
    }
    else
    {
      throw new Exception('Unsupported data type sent to send_xml()');
    }
    return $xml;
  }

  // Load a data model. Does not return the model, simply loads it
  // into the $models associative array property.
  protected function load_model ($model, $opts=array())
  { $nano = \Nano3\get_instance();
    $opts['parent'] = $this;
    $this->models[$model] = $nano->models->load($model, $opts);
  }

  /**
   * Get model options.
   *
   * Supports nested .type classifications.
   */
  protected function get_model_opts ($model, $opts=array(), $use_defaults=False)
  {
    $model = strtolower($model); // Force lowercase.
#    error_log("Looking for model options for '$model'");
    if (isset($this->model_opts) && is_array($this->model_opts))
    { // We have model options in the controller.
      if (isset($this->model_opts[$model]))
      { // There is model-specific options.
        $modeltype = Null;
        $modelopts = $this->model_opts[$model];
        if (is_array($modelopts))
        { 
          $opts += $modelopts;
          if (isset($modelopts['.type']))
          {
            $modeltype = $modelopts['.type'];
          }
        }
        elseif (is_string($modelopts))
        {
          $modeltype = $modelopts;
          if (!isset($opts['.type']))
          {
            $opts['.type'] = $modeltype;
          }
        }
        if (isset($modeltype))
        { // Groups start with a dot.
          $opts = $this->get_model_opts('.'.$modeltype, $opts);
          $func = 'get_'.$modeltype.'_model_opts';
          if (is_callable(array($this, $func)))
          {
#            error_log("  -- Calling $func() to get more options.");
            $addopts = $this->$func($model, $opts);
            if (isset($addopts) && is_array($addopts))
            {
#              error_log("  -- Options were found, adding to our set.");
              $opts += $addopts;
            }
          }
        }
      }
      elseif ($use_defaults)
      {
        $opts = $this->get_model_opts('.default', $opts);
      }
    }
#    error_log("Returning: ".json_encode($opts));
    return $opts;
  }

  // A wrapper for load_model with caching and more options.
  public function model ($model=Null, $opts=array())
  {
    $nano = \Nano3\get_instance();

    if (is_null($model))
    { // Assume the default model has the same name as the controller.
      $model = $this->name();
    }

    if (!isset($this->models[$model]))
    { // No model has been loaded yet.
      if ($this->use_model_cache)
      { // Check the session cache.
        $modelcache = $nano->sess->ModelCache;
        if (isset($modelcache) && isset($modelcache[$model]))
        { // The model cache, a double-edged sword. Use with care.
          return $this->models[$model] = $modelcache[$model];
        }
      }
      // Load any specific options.
      $opts = $this->get_model_opts($model, $opts, True);
      // Load any common options.
      $opts = $this->get_model_opts('.common', $opts);
      $this->load_model($model, $opts);
      if ($this->use_model_cache)
      {
        $modelObj = $this->models[$model];
        if (isset($modelObj) && is_callable(array($modelObj, 'allow_cache'))
          && $modelObj->allow_cache())
        {
          if (!isset($modelcache))
          {
            $modelcache = array();
          }
          $modelcache[$model] = $modelObj;
          $nano->sess->ModelCache = $modelcache;
        }
      }
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

  /*
   * Integration with nano.js
   */

  /**
   * Find a javascript file.
   */
  protected function find_script ($name, $opts=array())
  {
    if (isset($opts['exts']))
    {
      $exts = $opts['exts'];
    }
    else
    {
      $exts = $this->script_exts;
    }
    if (isset($opts['path']))
    {
      $path = $opts['path'];
    }
    else
    {
      $path = $this->script_path;
    }

    foreach ($path as $dir)
    {
      foreach ($exts as $ext)
      {
        $filename = $dir . '/' . $name . $ext;
        if (file_exists($filename))
        {
          return $filename;
        }
      }
    }
  }

  /**
   * Add a javascript file.
   */
  public function add_js ($name, $opts=array())
  {
    // Handle array input.
    if (is_array($name))
    {
      foreach ($name as $script)
      {
        $this->add_js($script, $opts);
      }
      return; // All done.
    }

    if (isset($this->script_added[$name]))
    { // We've already added this script or group.
      return;
    }

    // If this is a group, we process the group members.
    if (isset($this->script_groups[$name]))
    {
      foreach ($this->script_groups[$name] as $script)
      {
        $this->add_js($script, $opts);
      }
      $this->script_added[$name] = $name;
      return; // We've imported the group, let's leave now.
    }

    if (isset($this->script_opts[$name]))
    {
      $opts += $this->script_opts[$name];
    }

    if (isset($opts['file']))
    {
      $file = $opts['file'];
      if (!file_exists($file))
      {
        throw new Exception("Invalid script file specified for: '$name'.");
      }
    }
    else
    {
      $file = $this->find_script($name, $opts);
      if (!isset($file))
      {
        throw new Exception("Could not find script file for: '$name'.");
      } 
    }

    if (!isset($this->data['scripts']))
    {
      $this->data['scripts'] = array();
    }

    $this->data['scripts'][] = $file;
    $this->script_added[$name] = $file;
  }

  /**
   * Add a wrapper to a controller method that you can call from
   * the view (as a closure.) E.g. if you pass 'current_url' it will
   * create an object called $current_url that is a closure to our own
   * 'current_url()' method.
   */
  protected function addWrapper ($method)
  {
    $that = $this; // A wrapper to ourself.
    $closure = function () use ($that, $method)
    {
      $args = func_get_args();
      $meth = array($that, $method);
      return call_user_func_array($meth, $args);
    };
    $this->data[$method] = $closure;
  }

}

// End of base class.

