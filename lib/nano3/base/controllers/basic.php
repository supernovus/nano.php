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
 *   Gets a copy of the data to be sent/converted to XML and
 *   allows you to make adjustments to it before any conversion.
 *
 * Controller.send_html
 *   Gets a copy of the output text before sending it.
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

  /**
   * Defines the paths to look for Javascript files in when using
   * the add_js() method.
   *
   * The default is based on the layout used by the Nano.js project:
   *
   *  1.) 'scripts'
   *  2.) 'scripts/nano'
   *
   */
  protected $script_path = array('scripts', 'scripts/nano');

  /**
   * Defines the list of Javascript extensions to search for when using
   * the add_js() method.
   *
   * Override this on a global or per-controller basis to set the
   * preferred script extensions. The default load order is based on the
   * extenions used by the Nano.js project:
   *
   *   1.) .dist.js         - Distribution script.
   *   2.) .cc.js           - Closure Compiler script.
   *   3.) .min.js          - Minified script.
   *   4.) .js              - Raw script.
   *
   */
  protected $script_exts = array('.dist.js', '.cc.js', '.min.js', '.js');

  /** 
   * Override or extend this to provide groups of Javascript files to
   * include in one large set. We include a default group called '#common'
   * which provides the following scripts from the Nano.js project:
   *
   *  1.) jquery                  - The jQuery framework.
   *  2.) json2                   - The JSON object for older browsers.
   *  3.) json.jq                 - A .JSON() method for jQuery.
   *  4.) disabled.jq             - .enable() and .disable() jQuery methods.
   *  5.) exists.jq               - A .exists() method for jQuery.
   *
   */
  protected $script_groups = array
  ( // A set of common scripts included in the nano.js toolkit.
    '#common' => array('jquery','json2','json.jq','disabled.jq','exists.jq'),
  );

  /**
   * Define this in your own class to provide script specific overrides for
   * the $script_path and $script_exts properties. This is likely not needed
   * or recommended, but is kept for flexibility.
   */
  protected $script_opts = array();

  // Keep track of scripts and groups we've added, and don't duplicate stuff.
  protected $script_added = array();

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
    $nano = \Nano3\get_instance();
    $page = $nano->screens->load($screen, $data);
    $nano->callHook('Controller.send_html', array(&$page));
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
    $nano = \Nano3\get_instance();
    $nano->pragma('json no-cache');    // Don't cache this.
    $nano->callHook('Controller.send_json', array(&$data));
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
  { $nano = \Nano3\get_instance();
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

  /** 
   * Return our controller base name.
   */
  public function name ()
  {
    $nano = \Nano3\get_instance();
    return $nano->controllers->id($this);
  }

  /** 
   * Do we have an uploaded file?
   *
   * Uses the Nano3\File::hasUpload() static method.
   *
   * @param String $fieldname  The upload field to look for.
   */
  public function has_upload ($fieldname)
  {
    return \Nano3\Utils\File::hasUpload($fieldname);
  }

  /** 
   * Get an uploaded file. It will return Null if the upload does not exist.
   *
   * Uses the Nano3\File::getUpload() static method.
   *
   * @param String $fieldname   The upload field to get.
   */
  public function get_upload ($fieldname)
  {
    return \Nano3\Utils\File::getUpload($fieldname);
  }

  /**
   * Redirect to a URL.
   *
   * @param String $url    The URL to redirect to (optional, see below.)
   * @param Opts   $opts   Options to pass to Nano3\Plugins\URL::redirect().
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
    return \Nano3\Plugins\URL::redirect($url, $opts);
  }

  /**
   * Return our site URL. See Nano3\Plugins\URL::site_url() for details.
   */
  public function url ($ssl=Null, $port=Null)
  {
    return \Nano3\Plugins\URL::site_url($ssl, $port);
  }

  /**
   * Return our request URI. See Nano3\Plugins\URL::request_uri() for details.
   */
  public function request_uri ()
  {
    return \Nano3\Plugins\URL::request_uri();
  }

  /**
   * Return our current URL. See Nano3\Plugins\URL::current_url() for details.
   */
  public function current_url ()
  {
    return \Nano3\Plugins\URL::current_url();
  }

  /**
   * Send a file download to the client's browser.
   * Ends the current PHP process.
   *
   * See Nano3\Plugins\URL::download() for details.
   */
  public function download ($file, $opts=array())
  {
    return \Nano3\Plugins\URL::download($file, $opts);
  }

  /**
   * Find a javascript file, based on the known paths and extensions.
   * 
   * @param String $name   The name without path or extension (i.e. 'jquery')
   * @param Array $opts    Options that affect the search:
   *
   *   'exts'  If specified, overrides $this->script_exts for this search.
   *   'path'  If specified, overrides $this->script_path for this search.
   */
  public function find_script ($name, $opts=array())
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
   * Add a Javascript file or group to an array data variable called $scripts
   * which can be used in a view template to populate a collection of
   * <script src="..."></script> tags.
   *
   * We keep track of what files and groups have been added so that they
   * don't get duplicated. This is useful as multiple groups may have the
   * same script in them, and we don't want multiple <script/> tags added.
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

