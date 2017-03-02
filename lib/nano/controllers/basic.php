<?php

/** 
 * This class represents a controller foundation.
 *
 * The controller can have multiple models, and can load
 * templates consisting of a layout, and a screen.
 *
 * The contents of the screen will be made available as the
 * $view_content variable in the layout.
 *
 * You should create a base class to extend this that provides
 * any application-specific common controller methods.
 *
 * Your framework needs to define 'screens' and 'layouts' as view plugins.
 * This is as easy as:
 *
 *   $nano->screens = ['plugin'=>'views', 'dir'=>'./views/screens'];
 *   $nano->layouts = ['plugin'=>'views', 'dir'=>'./views/layouts'];
 *
 */

namespace Nano\Controllers;
use Nano\Exception;

abstract class Basic 
{
  use \Nano\Meta\ClassID;  // Adds $__classid and class_id()

  public $models  = [];     // Any Models we have loaded.

  protected $object_data = false; // If true, use an ArrayObject()
  protected $data = [];     // Our data to send to the templates.
  protected $screen;        // Set if needed, otherwise uses $this->name().

  protected $layout;        // The default layout to use.

  // The method to convert objects to JSON.
  protected $to_json_method = 'to_json';

  // The methood to convert objects to XML.
  protected $to_xml_method  = 'to_xml';

  // A list of constructors we've already called.
  protected $called_constructors = [];

  // Override this if you want an exception handler.
  protected $exception_handler;

  // Will be set on each routing operation.
  protected $current_context;

  // For internal use only.
  private $_constructed = false;

  /**
   * Provide a default __construct() method that can chain a bunch of
   * constructors together. 
   *
   * The list of constructors that will be called, and in what order, is
   * dependent on the existence of a class property called $constructors.
   * If the property exists, and is an array, then it is a list of keys,
   * which expect a method called __construct_{$key}_controller() is defined
   * in your class (likely via trait composing.)
   *
   * If the property does not exist, then we will get a list of all methods
   * matching __construct_{word}_controller() and will call them
   * all in whatever order they were defined in. 
   */
  public function __construct ($opts=[])
  {
    if ($this->object_data)
      $this->data = new \ArrayObject();

    // Assign an exception handler, if we specified one.
    if (isset($this->exception_handler))
    {
      $except = [$this, $this->exception_handler];
      if (is_callable($except))
      {
        set_exception_handler($except);
      }
    }

    // Populate our $__classid property.
    if (isset($opts['__classid']))
    {
      $this->__classid = $opts['__classid'];
    }

    if (property_exists($this, 'constructors') && is_array($this->constructors))
    { // Use a defined list of constructors.
      $constructors = $this->constructors;
      $fullname = False;
    }
    else
    { // Build a list of all known constructors, and call them.
      $constructors = preg_grep('/__construct_\w+_controller/i', 
        get_class_methods($this));
      $fullname = True;
    }

    $debug = $this->get_prop('debug', False);

    if ($debug)
      error_log("Constructor list: ".json_encode($constructors));

    $this->needs($constructors, $opts, $fullname);

    $this->_constructed = true;
  }

  public function init_route ($context)
  {
    $this->current_context = $context;

    if (property_exists($this, 'constructors') && is_array($this->constructors))
    {
      $constructors = $this->constructors;
      $fullname = False;
    }
    else
    {
      $constructors = preg_grep('/__init_\w+_controller/i',
        get_class_methods($this));
      $fullname = True;
    }

    $this->needs($constructors, $context, $fullname);
  }

  // Internal function to actually call the constructors.
  protected function needs ($constructor, $opts=[], $fullname=False, $nofail=false)
  {
    if (is_array($constructor))
    {
      foreach ($constructor as $const)
      {
        $this->needs($const, $opts, $fullname, $nofail);
      }
      return;
    }

    if ($fullname)
    {
      $method = $constructor;
    }
    else
    {
      if ($this->_constructed)
      {
        $method = "__init_{$constructor}_controller";
      }
      else
      {
        $method = "__construct_{$constructor}_controller";
      }
    }

    if 
    ( isset($this->called_constructors[$method]) 
      && $this->called_constructors[$method]
    ) return; // Skip already called constructors.

    if (method_exists($this, $method))
    {
      $this->called_constructors[$method] = true;
      $this->$method($opts);
    }
    elseif (!$nofail)
    {
      throw new Exception("Invalid constructor '$constructor' requested.");
    }
  }

  // Internal function to load a constructor only if it exists.
  protected function wants ($constructor, $opts=[], $fullname=False)
  {
    return $this->needs($constructor, $opts, $fullname, true);
  }

  // Used for composed traits. Check for a property, or return a default.
  public function get_prop ($property, $default=Null)
  {
    if (property_exists($this, $property))
      return $this->$property;
    else
      return $default;
  }

  // Set a property, if it exists.
  public function set_prop ($property, $value)
  {
    if (property_exists($this, $property))
      $this->$property = $value;
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
    $nano = \Nano\get_instance();

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

    // Allow for some preparation code before rendering the page.
    if (is_callable([$this, 'pre_render_page']))
    {
      $this->pre_render_page($screen, $layout, $opts);
    }

    // Make sure the 'parent' is set correctly.
    if (!isset($this->data['parent']))
      $this->data['parent'] = $this;

    // Okay, let's get the screen output.
    // The screen may use the $parent object to modify our data.
    $page = $nano->screens->load($screen, $this->data);

    // Now for post-page, pre-layout stuff.
    if (is_callable([$this, 'post_render_page']))
    {
      $this->post_render_page($page, $layout, $opts);
    }

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
    $nano = \Nano\get_instance();
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
    $nano = \Nano\get_instance();
    $nano->pragmas['json no-cache'];    // Don't cache this.

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

    if (is_array($data)) 
    { // Basic usage is to send simple arrays.
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
        $json = json_encode($data, $json_opts);
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
    $nano = \Nano\get_instance();
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
   * Return the requested Model object.
   *
   * @param Mixed $modelname       If set, must be a string, see below.
   * @param Array $modelopts       Options to pass to model, see below.
   * @param Array $loadopts        Options specific to this, see below.
   * 
   * If the $model is not specified or is Null, then we assume the
   * model has the same basename as the current controller.
   *
   * The $modelopts will be added to the parameters used in the class loader
   * (which will in turn be passed to the constructor of the Model class.)
   *
   * If the specified $model has been loaded already by this controller,
   * by default we will return the cached copy, ignoring any new options.
   *
   * The two $loadopts options we support are:
   *
   *   'forceNew'               If set to True, we will always create a new
   *                            instance of the model, even if we've loaded
   *                            it before. If caching is on, it will override
   *                            the previously loaded instance.
   *
   *  'noCache'                 If set to true, we will not cache the model
   *                            instance loaded by this call.
   *
   */
  public function model ($modelname=Null, $modelopts=[], $loadopts=[])
  {
    $nano = \Nano\get_instance();

    if (is_null($modelname))
    { // Assume the default model has the same name as the controller.
      $modelname = $this->name();
    }

    if 
    (
      !isset($this->models[$modelname]) ||
      (isset($loadopts['forceNew']) && $loadopts['forceNew'])
    )
    { 
      // If we have a populate_model_opts() method, call it.
      if (is_callable([$this, 'populate_model_opts']))
      {
        $modelopts = $this->populate_model_opts($modelname, $modelopts);
      }

      // Set our parent object.
      $modelopts['parent'] = $this;

      // Load the model instance.
      $instance = $nano->models->load($modelname, $modelopts);

      // Cache the results.
      if (!isset($loadopts['noCache']) || !$loadopts['noCache'])
      {
        $this->models[$modelname] = $instance;
      }

      return $instance;
    }

    return $this->models[$modelname];
  }

  /** 
   * Return our controller base name.
   */
  public function name ()
  {
    return $this->__classid;
  }

  /** 
   * Do we have an uploaded file?
   *
   * Uses the Nano\File::hasUpload() static method.
   *
   * @param String $fieldname  The upload field to look for.
   */
  public function has_upload ($fieldname)
  {
    return \Nano\Utils\File::hasUpload($fieldname);
  }

  /** 
   * Get an uploaded file. It will return Null if the upload does not exist.
   *
   * Uses the Nano\File::getUpload() static method.
   *
   * @param String $fieldname   The upload field to get.
   */
  public function get_upload ($fieldname)
  {
    return \Nano\Utils\File::getUpload($fieldname);
  }

  /**
   * Redirect to a URL.
   *
   * @param String $url    The URL to redirect to.
   * @param Opts   $opts   Options to pass to Nano\Plugins\URL::redirect().
   *
   */
  public function redirect ($url, $opts=array())
  {
    return \Nano\Plugins\URL::redirect($url, $opts);
  }

  /**
   * Does a route exist?
   */
  public function has_route ($route)
  {
    $nano = \Nano4\get_instance();
    return $nano->router->has($route);
  }

  /**
   * Go to a route.
   *
   * This will throw an Exception if the route does not exist.
   */
  public function go ($page, $params=[], $opts=[])
  {
    $nano = \Nano\get_instance();

    if (isset($opts['sub']) && $opts['sub'])
    {
      $page = $this->__classid . '_' . $page;
      unset($opts['sub']);
    }

    if ($nano->router->has($page))
    {
      $nano->router->go($page, $params, $opts);
    }
    else
    {
      throw new Exception("invalid target '$page' for Controller::go()");
    }
  }

  /**
   * Get a route URI.
   * variables, and once again, can only be used with no parameters.
   */
  public function get_uri ($page, $params=[])
  {
#    error_log("get_uri('$page',".json_encode($params).")");
    $nano = \Nano\get_instance();
    if ($nano->router->has($page))
    {
      return $nano->router->build($page, $params);
    }
    else
    {
      throw new Exception("invalid target '$page' for Controller::get_uri()");
    }
  }

  /**
   * Get a sub-URI of this controller.
   */
  public function get_suburi ($subpage, $params=[])
  {
    $ourid = $this->__classid;
    if (is_null($subpage))
      return $this->get_uri($ourid, $params);
    else
      return $this->get_uri($ourid.'_'.$subpage, $params);
  }

  /**
   * Return our site URL. See Nano\Plugins\URL::site_url() for details.
   */
  public function url ($ssl=Null, $port=Null)
  {
    return \Nano\Plugins\URL::site_url($ssl, $port);
  }

  /**
   * Return our request URI. See Nano\Plugins\URL::request_uri() for details.
   */
  public function request_uri ()
  {
    return \Nano\Plugins\URL::request_uri();
  }

  /**
   * Return our current URL. See Nano\Plugins\URL::current_url() for details.
   */
  public function current_url ()
  {
    return \Nano\Plugins\URL::current_url();
  }

  /**
   * Send a file download to the client's browser.
   * Ends the current PHP process.
   *
   * See Nano\Plugins\URL::download() for details.
   */
  public function download ($file, $opts=array())
  {
    return \Nano\Plugins\URL::download($file, $opts);
  }

  /**
   * Add a wrapper to a controller method that you can call from
   * a view template (as a Callable.)
   *
   * @param String $method   The method we want to wrap into a closure.
   * @param String $varname  (Optional) The name of the callable for the views.
   * @param Bool   $closure  (Default False) If true, use a closure.
   *
   * If $varname is not specified, it will be the same as the $method.
   *
   * As an example, a call of $this->addWrapper('url'); will create
   * a callable called $url, that when called, will make a call to $this->url()
   * with the specified parameters.
   */
  protected function addWrapper ($method, $varname=Null, $closure=False)
  {
    if (is_null($varname))
      $varname = $method;
    if ($closure)
    { // A closure may be desired in some cases.
      $that = $this; // A wrapper to ourself.
      $closure = function () use ($that, $method)
      {
        $args = func_get_args();
        $meth = [$that, $method];
        return call_user_func_array($meth, $args);
      };
      $this->data[$varname] = $closure;
    }
    else
    { // Callables are simpler than closures in most cases.
      $this->data[$varname] = [$this, $method];
    }
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

