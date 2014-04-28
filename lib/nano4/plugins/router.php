<?php

namespace Nano4\Plugins;
use Nano4\Exception;

/**
 * Routing Dispatcher.
 * 
 * Matches routes based on rules.
 * Inspired by Webtoo (w/Router::Simple) and PHP-Router.
 */
class Router
{
  protected $routes = [];  // A flat list of routes.
  protected $named  = [];  // Any named routes, for reverse generation.
  protected $default;      // The default route, must be explicitly set.

  public $base_uri = '';

  public $log = False;

  public $default_filter = "([\w\-\~\.]+)"; // Used by default.

  public function known_routes ()
  {
    return array_keys($this->named);
  }

  public function __construct ($opts=[])
  {
    if (isset($opts['base_uri']))
    {
      $this->base_uri($opts['base_uri']);
    }
    elseif (isset($opts['auto_prefix']) && $opts['auto_prefix'])
    {
      $this->auto_prefix();
    }

    if (isset($opts['extend']) && $opts['extend'])
    { // Register some helpers into the Nano object.
      $nano = \Nano4\get_instance();
      $nano->addMethod('dispatch',     [$this, 'route']);
      $nano->addMethod('addRoute',     [$this, 'add']);
      $nano->addMethod('addRedirect',  [$this, 'redirect']);
      $nano->addMethod('addPage',      [$this, 'display']);
      $nano->addMethod('setDefault',   [$this, 'setDefault']);
    }
  }

  /**
   * Set the base_uri.
   */
  public function base_uri ($newval=Null)
  {
    if (isset($newval))
    {
      $this->base_uri = rtrim($newval, "/");
    }
    return $this->base_uri;
  }

  /**
   * Automatically set the URL prefix based on our SCRIPT_NAME.
   */
  public function auto_prefix ()
  {
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    $this->base_uri($dir);
  }

  /**
   * Add a route
   */
  public function add ($route, $is_default=False, $add_it=True)
  {
    if ($route instanceof Route)
    { // It's a route object.

      // Ensure proper parentage.
      $route->parent = $this;

      // Add it to our list of routes.
      if ($add_it)
        $this->routes[] = $route;

      /// Handle named routes.
      if (isset($route->name) && !isset($this->named[$route->name]))
        $this->named[$route->name] = $route;

      // Handle the default route.
      if ($is_default)
        $this->default = $route;

      return $route;
    }
    elseif (is_array($route))
    { // It's options for constructing a route.
      $route = new Route($route);
      return $this->add($route, $is_default, $add_it); // magical recursion.
    }
    elseif (is_string($route))
    { 
      $ropts = ['uri' => $route];      
      if (is_bool($is_default))
      { // Assume the first parameter is the controller, and that the
        // URI is the same as the controller name (but with slashes.)
        $ropts['controller'] = $route;
        $ropts['name']       = $route;
        $ropts['uri']        = "/$route/";
      }
      elseif (is_array($is_default))
      { // Both controller and action specified.
        $ropts['controller'] = $ctrl   = $is_default[0];
        $ropts['action']     = $action = $is_default[1];
        $ropts['name'] = $ctrl.'_'.preg_replace('/^handle_/', '', $action);
      }
      elseif (is_string($is_default))
      { // Just a controller specified.
        $ropts['controller'] = $ropts['name'] = $is_default;
      }
      else
      { // What did you send?
        throw new \Exception("Invalid controller specified in Route::add()");
      }

      // If the third parameter is a string or array, it's allowed methods.
      if (is_string($add_it))
        $ropts['methods'] = [$add_it];
      elseif (is_array($add_it))
        $ropts['methods'] = $add_it;

      // Okay, build the route, and add it.
      $route = new Route($ropts);
      return $this->add($route);
    }
    else
    {
      throw new \Exception("Unrecognized route sent to Router::add()");
    }
  }

  /**
   * Set a default controller. This will not be checked in the
   * normal route test, and will only be used if no other routes matched.
   */
  public function setDefault ($route)
  {
    if ($route instanceof Route)
    {
      return $this->add($route, True, False);
    }
    elseif (is_array($route))
    { // It's options for constructing a route.
      $route = new Route($route);
      return $this->add($route, True, False); // magical recursion.
    }
    elseif (is_string($route))
    { // Expects a controller with the handle_default() method.
      $route = new Route(
      [
        'controller' => $route,
        'name'       => $route,
      ]);
      return $this->add($route, True, False);
    }
  }

  /**
   * Add a redirect rule.
   */
  public function redirect ($from_uri, $to_uri, $opts=[])
  {
    $short   = isset($opts['short'])   ? $opts['short']   : False;
    $default = isset($opts['default']) ? $opts['default'] : False;
    $isroute = isset($opts['route'])   ? $opts['route']   : False;

    // Determine the appropriate target based on the 'short' option.
    $target = $short ? $to_uri : $this->base_uri . $to_uri;

    $this->add(
    [
      'uri'               => $from_uri,
      'redirect'          => $target,
      'redirect_is_route' => $isroute,
    ], $default);
  }

  /**
   * Display a view without an underlying controller.
   */
  public function display ($path, $view, $is_default=False)
  {
    $def = ['uri'=>$path];
    if (is_array($view))
    {
      $def['view_loader'] = $view[0];
      $def['view']        = $view[1];
    }
    else
    {
      $def['view'] = $view;
    }
    $this->add($def, $is_default);
  }

  /**
   * See if we can match a route against a URI and method.
   *
   * Returns a RouteContext object.
   *
   * If there is no default controller specified, and no route matches,
   * it will return Null.
   */
  public function match ($uri=Null, $method=Null)
  {
    if (is_null($uri))
    {
      $uri = $_SERVER['REQUEST_URI'];
      if (($pos = strpos($uri, '?')) !== False)
      {
        $uri = substr($uri, 0, $pos);
      }
    }
    if (is_null($method))
    { // Use the current request method.
      $method = $_SERVER['REQUEST_METHOD'];
    }
    else
    { // Force uppercase.
      $method = strtoupper($method);
    }

    $path = explode('/', $uri);

    foreach ($this->routes as $route)
    {
      $routeinfo = $route->match($uri, $method);

      if (isset($routeinfo))
      {
        if ($route->strict)
        {
          if ($method == 'GET' && isset($_GET))
            $request = $_GET;
          elseif ($method == 'POST' && isset($_POST))
            $request = $_POST;
          else
            $request = $_REQUEST;
        }
        else
        {
          $request = $_REQUEST;
        }

        // Let's get the body params if applicable.
        if (strtolower($_SERVER['CONTENT_TYPE']) == "application/json")
        {
          $body_params = json_decode(file_get_contents("php://input"), true);
        }
        else
        {
          $body_params = [];
        }

        $context = new RouteContext(
        [
          'router'         => $this,
          'route'          => $route,
          'path'           => $path,
          'request_params' => $request,
          'path_params'    => $routeinfo,
          'body_params'    => $body_params,
          'method'         => $method,
        ]);

        return $context;

      } // if ($routeinfo)
    } // foreach ($routes)

    // If we reached here, no matching route was found.
    // Let's send the default route.
    if (isset($this->default))
    {
      $context = new RouteContext(
      [
        'router'         => $this,
        'route'          => $this->default,
        'path'           => $path,
        'request_params' => $_REQUEST,
        'method'         => $method,
      ]);

      return $context;
    }
  } // function match()

  /**
   * The primary frontend function for starting the routing.
   */
  public function route ($uri=Null, $method=Null)
  {
    $nano = \Nano4\get_instance();
    $context = $this->match($uri, $method);
    if (isset($context))
    {
      $route = $context->route;
      if ($this->log && $route->name)
        error_log("Dispatching to {$route->name}");

      if ($route->redirect)
      { // Whether we redirect to a URL, or go to a known route,
        // depends on the redirect_is_route setting.
        if ($route->redirect_is_route)
        {
          $this->go($route->redirect, $context->path_params);
        }
        else
        {
          $nano->url->redirect($route->redirect);
        }
      }
      elseif ($route->view)
      { // We're loading a view.
        if (isset($route->view_status))
        {
          http_response_code($route->view_status);
        }
        $loader = $route->view_loader;
        return $nano->lib[$loader]->load($route->view, $context->to_array());
      }
      elseif ($route->controller)
      {
        // We consider it a fatal error if the controller doesn't exist.
        $controller = $nano->controllers->load($route->controller);

        if (is_callable([$controller, 'init_route']))
        {
          $controller->init_route($context);
        }

        $action = $route->action;
        if (is_callable([$controller, $action]))
        {
          return $controller->$action($context);
        }
        else
        {
          throw new \Exception("Controller action $action not found.");
        }
      }
      else
      {
        throw new \Exception("Invalid Route definition.");
      }
    }
    else
    {
      throw new \Exception("No route matched, and no default controller set.");
    }
  }

  /**
   * Build a URI for a named route.
   */
  public function build ($routeName, $params=[], $opts=[])
  {
    if (!isset($this->named[$routeName]))
    {
      throw new 
        \Exception("No named route '$routeName' in call to Router::build()");
    }
    unset($opts['fulluri']); // Keep our sanity.
    $route_uri = $this->named[$routeName]->build($params, $opts);
    if (isset($opts['short']) && $opts['short'])
      return $route_uri;
    else
      return $this->base_uri . $route_uri;
  }

  /**
   * Redirect the browser to a known route, with the appropriate parameters.
   */
  public function go ($routeName, $params=[], $ropts=[], $bopts=[])
  {
    $uri  = $this->build($routeName, $params, $bopts);
    $nano = \Nano4\get_instance();
    $nano->url->redirect($uri, $ropts);
  }

  /**
   * Check to see if we know about a named route.
   */
  public function has ($routeName)
  {
    return isset($this->named[$routeName]);
  }

}

/**
 * A shared trait offering a really simple constructor.
 */
Trait RouteConstructor
{
  public function __construct ($opts=[])
  {
    foreach (get_object_vars($this) as $field => $default)
    {
      if (isset($opts[$field]))
        $this->$field = $opts[$field];
    }
  }
}

/**
 * An individual Route.
 */
class Route
{
  use RouteConstructor;

  public $parent;                                    // The Router object.
  public $name;                                      // Optional name.
  public $uri         = '';                          // URI to match.
  public $controller;                                // Target controller.
  public $action      = 'handle_default';            // Target action.
  public $strict      = False;                       // Request data source.
  public $redirect;                                  // If set, we redirect.
  public $view_loader = 'views';                     // Used with 'view'.
  public $view;                                      // A view to load.
  public $view_status;                               // HTTP status override.

  public $methods = ['GET','POST','PUT','DELETE'];   // Supported methods.

  public $redirect_is_route = False;                 // Redirect to a route?

  protected $filters = [];                           // Parameter filters.

  protected function uri_regex ()
  {
    return preg_replace_callback
    (
      "/:([\w-]+)/", 
      [$this, 'substitute_filter'], 
      $this->uri
    );
  }

  protected function substitute_filter ($matches)
  {
    if (isset($matches[1]) && isset($this->filters[$matches[1]]))
    {
      return $this->filters[$matches[1]];
    }
    return $this->parent->default_filter; // The default filter.
  }

  public function match ($uri, $method)
  {

#    error_log("Checking $uri against " . $this->uri);

    if (! in_array($method, $this->methods)) return; // Doesn't match method.

#    error_log("  -- our method matched.");

    $match = "@^"
           . $this->parent->base_uri
           . $this->uri_regex()
           . "*$@i";

#    error_log("searching with regex: $match");

    if (! preg_match($match, $uri, $matches)) return; // Doesn't match URI.

#    error_log(" -- It matched!");

    $params = [];

    if (preg_match_all("/:([\w-]+)/", $this->uri, $argument_keys))
    {
      $argument_keys = $argument_keys[1];
      foreach ($argument_keys as $key => $name)
      {
        if (isset($matches[$key + 1]))
        {
          $params[$name] = $matches[$key + 1];
        }
      }
    }

    return $params;

  }

  public function build ($params=[], $opts=[])
  {
    $uri = $this->uri;

    // First, we replace any sent parameters.
    if ($params && preg_match_all("/:([\w-]+)/", $uri, $param_keys))
    {
      $param_keys = $param_keys[1];
      foreach ($param_keys as $key)
      {
        if (isset($params[$key]))
        {
          $uri = preg_replace("/:([\w-]+)/", $params[$key], $uri, 1);
        }
      }
    }

    // Okay, a sanity check. If there are still placeholders, we have
    // a problem, and cannot continue.
    // Pass ['strict'=>False] to make this non-fatal.
    $strict = isset($opts['strict']) ? $opts['strict'] : True;
    if (preg_match_all("/:([\w-]+)/", $uri, $not_found))
    {
      $not_found = $not_found[1];
      $not_found = join(', ', $not_found);
      if ($strict)
      {
        throw new \Exception("Route::build() is missing: $not_found");
      }
      else
      {
        return Null;
      }
    }

    if (isset($opts['fulluri']) && $opts['fulluri'])
      $uri = $this->parent->base_uri . $uri;

    return $uri;
  }

  // For chaining Routes.
  public function add ($suburi, $action=Null, $rechain=False)
  {
    $ctrl = $this->controller;
    $baseuri = rtrim($this->uri, "/");
    if (is_array($action))
    {
      $ropts = $action;
      $ropts['uri'] = $baseuri . $suburi;
      if (!isset($ropts['controller']))
        $ropts['controller'] = $ctrl;
    }
    elseif (is_string($action))
    { // Specified the action, using our controller and path.
      $ropts =
      [
        'uri'        => $baseuri . $suburi,
        'action'     => $action,
        'name'       => $ctrl . '_' . preg_replace('/^handle_/', '', $action),
        'controller' => $ctrl,
      ];
    }
    else
    { // Action will be 'handle_suburi', don't include the / in the $suburi.
      $ropts =
      [
        'uri'        => "$baseuri/$suburi/",
        'action'     => 'handle_' . $suburi,
        'name'       => $ctrl . '_' . $suburi,
        'controller' => $ctrl,
      ];
    }

    // If the third parameter is a string or array, it's allowed methods.
    if (!is_bool($rechain))
    {
      if (is_string($rechain))
        $ropts['methods'] = [$rechain];
      elseif (is_array($rechain))
        $ropts['methods'] = $rechain;
      // Reset rechain back to a boolean value.
      $rechain = False;
    }

    // Build the sub-route with our compiled options.
    $subroute = new Route($ropts);
    $this->parent->add($subroute);

    if ($rechain)
      return $subroute;
    else
      return $this;
  }

}

/**
 * A routing context object. Sent to controllers.
 */
class RouteContext implements \ArrayAccess
{
  use RouteConstructor;

  public $router;              // The router object.
  public $route;               // The route object.
  public $path           = []; // The URI path elements.
  public $request_params = []; // The $_REQUEST, $_GET or $_POST data.
  public $path_params    = []; // Parameters specified in the URI.
  public $body_params    = []; // Params found in a JSON body, if applicable.
  public $method;              // The HTTP method used.

  // Convert this into a simple array structure.
  public function to_array ($opts=[])
  {
    $array =  $this->path_params + $this->body_params + $this->request_params;
    $array['_context'] = $this;
    return $array;
  }

  public function offsetGet ($offset)
  {
    if (array_key_exists($offset, $this->path_params))
    {
      return $this->path_params[$offset];
    }
    elseif (array_key_exists($offset, $this->body_params))
    {
      return $this->body_params[$offset];
    }
    elseif (array_key_exists($offset, $this->request_params))
    {
      return $this->request_params[$offset];
    }
    else
    {
      return Null;
    }
  }

  public function offsetSet ($offset, $value)
  {
    throw new Exception ("Context parameters are read only.");
  }

  public function offsetExists ($offset)
  {
    if (array_key_exists($offset, $this->path_params))
    {
      return True;
    }
    elseif (array_key_exists($offset, $this->body_params))
    {
      return True;
    }
    elseif (array_key_exists($offset, $this->request_params))
    {
      return True;
    }
    else
    {
      return False;
    }
  }

  public function offsetUnset ($offset)
  {
    throw new Exception ("Cannot unset a context parameter.");
  }

}

