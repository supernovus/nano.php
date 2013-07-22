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
 * Your framework needs to define 'screens' and 'layouts' as view plugins.
 * This is as easy as:
 *
 *   $nano->screens = ['plugin'=>'views', 'dir'=>'./views/screens'];
 *   $nano->layouts = ['plugin'=>'views', 'dir'=>'./views/layouts'];
 *
 * It now has integration with the nano.js project, and can populate
 * the $scripts template variable with appropriate javascript files.
 */

namespace Nano4\Controllers;
use Nano4\Exception;

abstract class Basic 
{
  public $models = array(); // Any Models we have loaded.

  protected $data = [];     // Our data to send to the templates.
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

  /**
   * Resources represent external files, such as scripts, stylesheets, etc.
   * They are managed through a generic system that allows for easy future
   * expansion.
   *
   * Each group as a resource id, such as 'js', 'css', etc, and the following
   * definitions:
   *
   *   name:      The variable name for templates.
   *   path:      An array of paths to look for resource files in.
   *   exts:      An array of extensions to look for resource files.
   *   groups:    An array of arrays, each being named groups.
   *              It is recommended to prefix groups with an identifier
   *              such as '#'.  We define a group called #common which
   *              represents a small set of plugins from the Nano.js project.
   *   added:     An empty array, will be populated by use_resource();
   *
   *  A group for one resource type MAY in fact depend on a resource of another
   *  type. For instance, you may have a Javascript file that depends on a
   *  CSS stylesheet being loaded. You can define a rule that will include it,
   *  by using a 'type:name' format, such as 'css:foobar'.
   *
   */
  protected $resources = array
  (
    'js' => array
    (
      'name' => 'scripts',
      'path' => array('scripts', 'scripts/nano'),
      'exts' => array('.dist.js', '.cc.js', '.min.js', '.js'),
      'groups' => array
      (
        '#common' => array
        (
          'jquery', 
          'json3', 
          'json.jq', 
          'disabled.jq',
          'exists.jq',
        )
      ),
      'added' => array(), 
    ),
    'css' => array
    (
      'name'   => 'stylesheets',
      'path'   => array('style'),
      'exts'   => array('.css'),
      'groups' => array(),
      'added'  => array(),
    ),
  );

  /**
   * Get a page path definition from the Nano options.
   * If not defined, we return '/'.
   */
  public function get_page ($name)
  {
    $nano = \Nano4\get_instance();
    $page = $nano["page.$name"];
    if (isset($page))
    {
      return $page;
    }
    return '/';
  }

  /**
   * Display the contents of a screen, typically within a common layout.
   * We use the $data class member as the array of variables to pass to
   * the template.
   *
   * @params Array $opts    Specific options to override behavior:
   *
   *   'screen'     If set, overrides the screen.
   *   'layout'     If set, overrides the layout.
   *
   * If 'screen' is not set, then we use $this->screen if it is set.
   * If neither 'screen' or $this->screen is set, we look for a screen
   * with the same basename as the controller.
   */
  public function display ($opts=array())
  {
    // Get Nano.
    $nano = \Nano4\get_instance();

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
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      return $page;
    }
  }

  /**
   * Sometimes we don't want to display a primary screen with a layout,
   * but instead a sub-screen, with no layout, and using specified data.
   *
   * @param String $screen    The name of the screen view to use.
   * @param Array  $data      (Optional) Variables to send to the screen view.
   *
   * The $data defines the variables that will be made available to the
   * screen view template. If you do not specify a $data array, then the
   * $this->data class member will be used instead.
   */
  public function send_html ($screen, $data=Null)
  {
    if (is_null($data))
      $data = $this->data;
    $nano = \Nano4\get_instance();
    $page = $nano->screens->load($screen, $data);
    return $page;
  }

  /** 
   * Sometimes we want to send JSON data instead of a template.
   *
   * @param Mixed $data         The data to send (see below)
   * @param Mixed $opts         Options, see below.
   *
   * If the $data is a PHP Array, then it will be processed using the
   * json_encode() function. In this case, there is one recognized
   * option (which is only applicable with PHP 5.4 or higher)
   *
   *   'fancy'     If set to True, we will use human readable formatting
   *               on the JSON string (aka Pretty Printing.)
   *
   * If the $data is an Object, then it must have a method as per the
   * $this->to_json_method (default: 'to_json'), which will be called, and
   * passed the $opts as its first parameter.
   *
   * If the $data is a string, it will be assumed to be a valid JSON string,
   * and will be sent as is.
   *
   * Anything else will fail and throw an Exception.
   */
  public function send_json ($data, $opts=array())
  { 
    $nano = \Nano4\get_instance();
    $nano->pragmas['json no-cache'];    // Don't cache this.
    if (is_array($data)) 
    { // Basic usage is to send simple arrays.
      $json_opts = 0;
      if 
      (
        isset($opts['fancy']) 
        && $opts['fancy'] 
        && defined('JSON_PRETTY_PRINT') // PHP 5.4+
      )
      {
        $json_opts = JSON_PRETTY_PRINT;
      }
      $json = json_encode($data, $json_opts);
    }
    elseif (is_string($data))
    { // A passthrough for JSON strings.
      $json = $data;
    }
    elseif (is_object($data))
    { // Magic for converting objects to JSON.
      $method = $this->to_json_method;
      if (is_callable(array($data, $method)))
        $json = $data->$method($opts);
      else
        throw new Exception('Unsupported object sent to send_json()');
    }
    else
    {
      throw new Exception('Unsupported data type sent to send_json()');
    }
    return $json;
  }

  /** 
   * Sometimes we want to send XML data instead of a template.
   *
   * @param Mixed $data        The data to send (see below)
   * @param Mixed $opts        Options, used in some cases (see below)
   *
   * If $data is a string, we assume it's valid XML and pass it through.
   *
   * If $data is a SimpleXML Element, DOMDocument or DOMElement, we use the
   * native calls of those libraries to convert it to an XML string.
   * One caveat: we assume that DOMElement objects have an ownerDocument,
   * and if they don't, this method will fail.
   *
   * If the $data is another kind of object, and has a method as per the
   * $this->to_xml_method (default: 'to_xml') then it will be called with
   * the $opts as its first parameter.
   *
   * Anything else will throw an Exception.
   */
  public function send_xml ($data, $opts=Null)
  {
    $nano = \Nano4\get_instance();
    $nano->pragmas['xml no-cache'];
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
        $xml = $data->$method($opts);
      else
        throw new Exception('Unsupported object sent to send_xml()');
    }
    else
    {
      throw new Exception('Unsupported data type sent to send_xml()');
    }
    return $xml;
  }

  /** 
   * Load a data model into the $this->models associative array property.
   *
   * @param String $model_name      The name of the model to load.
   * @param Array  $opts            Options to be used by the class loader.
   *
   * The $model_name model will loaded, and saved as $this->models[$model_name]
   */
  protected function load_model ($model, $opts=array())
  { $nano = \Nano4\get_instance();
    $opts['parent'] = $this;
    return $this->models[$model] = $nano->models->load($model, $opts);
  }

  /**
   * Get model options from our $this->model_opts definitions.
   *
   * @param String $name              The model/group to look up options for.
   * @param Array  $opts              Current/overridden options.
   * @param Bool   $defaults          If True, use default options.
   *
   * If the $defaults is True, and we cannot find a set of options for the
   * specified model, then we will look for a set of options called '.default'
   * and use that instead.
   *
   * A special option called '.type' allows for nesting option defintions.
   * If set in any level, an option group with the name of the '.type' will be 
   * looked up, and any options in its definition will be added (if they don't
   * already exist.) Groups may have their own '.type' option, allowing for
   * multiple levels of nesting.
   *
   * The '.type' rule MUST NOT start with a dot. The group definition MUST
   * start with a dot. The dot will be assumed on all groups.
   *
   * So if a '.type' option is set to 'common', then a group called '.common'
   * will be inherited from.
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

  /** 
   * Return the requested Model object.
   *
   * @param Mixed $model      If set, must be a string, see below.
   * @param Array $opts       Options, see below.
   * 
   * If the $model is not specified or is Null, then we assume the
   * model has the same basename as the current controller.
   *
   * The $opts will be added to the list of options used in the class loader
   * (which will in turn be passed to the constructor of the Model class.)
   *
   * If the specified $model has been loaded already by this controller,
   * regardless of the $opts, the loaded copy will be returned.
   *
   * The same applies for if we have enabled the Session Model Cache (which
   * is experimental and only recommended if you really know what you are
   * doing, as otherwise you could have some seriously messed up results.)
   *
   * If the $model has not been loaded, then we will populate the $opts
   * by first looking up an option set with the $model name, and if not found,
   * using the '.default' group instead. Then we will add any options from
   * the '.common' group (if it exists) that have not been overridden.
   *
   * Finally, we will call load_model() with the $model and populated $opts.
   */
  public function model ($model=Null, $opts=array())
  {
    $nano = \Nano4\get_instance();

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

  /** 
   * Return our controller base name.
   */
  public function name ()
  {
    $nano = \Nano4\get_instance();
    return $nano->controllers->class_id($this);
  }

  /** 
   * Do we have an uploaded file?
   *
   * Uses the Nano4\File::hasUpload() static method.
   *
   * @param String $fieldname  The upload field to look for.
   */
  public function has_upload ($fieldname)
  {
    return \Nano4\Utils\File::hasUpload($fieldname);
  }

  /** 
   * Get an uploaded file. It will return Null if the upload does not exist.
   *
   * Uses the Nano4\File::getUpload() static method.
   *
   * @param String $fieldname   The upload field to get.
   */
  public function get_upload ($fieldname)
  {
    return \Nano4\Utils\File::getUpload($fieldname);
  }

  /**
   * Redirect to a URL.
   *
   * @param String $url    The URL to redirect to (optional, see below.)
   * @param Opts   $opts   Options to pass to Nano4\Plugins\URL::redirect().
   *
   * If the $url parameter is not defined, we will use the $this->default_url
   * property instead. If neither are defined, this will fail.
   */
  public function redirect ($url=Null, $opts=array())
  {
    if (is_null($url))
    {
      $url = $this->default_url;
    }
    return \Nano4\Plugins\URL::redirect($url, $opts);
  }

  /**
   * Return our site URL. See Nano4\Plugins\URL::site_url() for details.
   */
  public function url ($ssl=Null, $port=Null)
  {
    return \Nano4\Plugins\URL::site_url($ssl, $port);
  }

  /**
   * Return our request URI. See Nano4\Plugins\URL::request_uri() for details.
   */
  public function request_uri ()
  {
    return \Nano4\Plugins\URL::request_uri();
  }

  /**
   * Return our current URL. See Nano4\Plugins\URL::current_url() for details.
   */
  public function current_url ()
  {
    return \Nano4\Plugins\URL::current_url();
  }

  /**
   * Send a file download to the client's browser.
   * Ends the current PHP process.
   *
   * See Nano4\Plugins\URL::download() for details.
   */
  public function download ($file, $opts=array())
  {
    return \Nano4\Plugins\URL::download($file, $opts);
  }

  /**
   * Find a resource file, based on known paths and extensions.
   *
   * @param String $type    The resource type.
   * @param String $name    The resource name without path or extension.
   */
  public function find_resource ($type, $name)
  {
    if (!isset($this->resources[$type]))
      return Null; 

    $exts = $this->resources[$type]['exts'];
    $path = $this->resources[$type]['path'];

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
   * Add a resource file to an array of resources for use in view templates.
   *
   * @param String $type    The resource type.
   * @param String $name    The resource or group name.
   */
  public function use_resource ($type, $name)
  {
    // Make sure it's a valid resource type.
    if (!isset($this->resources[$type])) return False;

    // Handle array input.
    if (is_array($name))
    {
      foreach ($name as $res)
      {
        $this->use_resource($type, $res);
      }
      return True; // All done.
    }

    if (isset($this->resources[$type]['added'][$name]))
    {
      return True;
    }

    // If this is a group, we process the group members.
    if (isset($this->resources[$type]['groups'][$name]))
    {
      $group = $this->resources[$type]['groups'][$name];
      foreach ($group as $res)
      {
        if (strpos($res, ':') === False)
        {
          $this->use_resource($type, $res);
        }
        else
        {
          $parts = explode(':', $res);
          $etype = $parts[0];
          $ename = $parts[1];
          $this->use_resource($etype, $ename);
        }
      }
      $this->resources[$type]['added'][$name] = $name;
      return True; // We've imported the group, let's leave now.
    }

    $file = $this->find_resource($type, $name);
    if (!isset($file))
    {
      error_log("Could not find $type file for: '$name'.");
      return False;
    }

    $resname = $this->resources[$type]['name'];

#    error_log("Adding $type '$name' to $resname as $file");

    if (!isset($this->data[$resname]))
    {
      $this->data[$resname] = array();
    }

    $this->data[$resname][] = $file;
    $this->resources[$type]['added'][$name] = $file;
    return True;
  }

  /**
   * Add a Javascript file or group to our used resources.
   */
  public function add_js ($name)
  {
    return $this->use_resource('js', $name);
  }

  /**
   * Add a CSS stylesheet file or group to our used resources.
   */
  public function add_css ($name)
  {
    return $this->use_resource('css', $name);
  }

  /**
   * Add a wrapper to a controller method that you can call from
   * a view template (as a closure.)
   *
   * @param String $method   The method we want to wrap into a closure.
   * @param String $closure  (Optional) The name of the closure for the views.
   *
   * If $closure is not specified, it will be the same as the $method.
   *
   * As an example of both, a call of $this->addWrapper('url'); will create
   * a closure called $url, that when called, will make a call to $this->url()
   * with the specified parameters.
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

  /**
   * Is the server using a POST request?
   */
  public function is_post ()
  {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST))
    {
      return True;
    }
    return False;
  }

}

// End of base class.

