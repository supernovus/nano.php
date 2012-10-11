<?php

namespace Nano3\Plugins;
use Nano3\Exception;

/**
 * Remove Invisible Characters (Function from CodeIgniter)
 *
 * This prevents sandwiching null characters
 * between ascii characters, like Java\0script.
 *
 * @access	public
 * @param	string
 * @return	string
 */
function remove_invisible_characters($str, $url_encoded = TRUE)
{
	$non_displayables = array();
		
	// every control character except newline (dec 10)
	// carriage return (dec 13), and horizontal tab (dec 09)
		
	if ($url_encoded)
	{
		$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
		$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
	}
		
	$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

	do
	{
		$str = preg_replace($non_displayables, '', $str, -1, $count);
	}
	while ($count);

	return $str;
}

/**
 * Return the PATH information.
 */
function get_path ()
{ // Extracted from get_routing for use elsewhere.
  #$path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO');
  $path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
  $path = explode('?', $path)[0]; // strip off any queries.
  $path = remove_invisible_characters($path, FALSE);
#  error_log("found path: $path");
  return $path;
}

/**
 * Return an array representing the individual elements of a path.
 *
 * @param String $path   The path to parse into an array.
 */
function get_routing ($path=null) 
{ // We use the PATH_INFO to determine our routing.
  if (is_null($path))
    $path = get_path();
  $paths = array();
  if ($path != '/' || $path != '') {
    foreach (explode("/", preg_replace("|/*(.+?)/$|", "\\1", $path)) as $val)
    { $val = trim($val);
      if ($val != '')
      {
        $paths[] = $val;
      }
    }
  }
  return $paths;
}

/**
 * Dispatch to Controllers based on rules.
 *
 * Most rules match specific URL patterns, and so one script can dispatch
 * to many different controllers and methods, depending on the current URL.
 */
class Dispatch
{
  protected $controllers = array();                // Known controllers.
  protected $default_method = 'handle_dispatch';   // Default method to call.

  public $url_prefix;                              // Use if in subdir.

  /** 
   * Set the URL prefix.
   *
   * @param String $dir    URL Prefix.
   */
  public function url_prefix ($dir=Null)
  {
    if (is_null($dir))
    {
      $dir = dirname($_SERVER['SCRIPT_NAME']);
    }
    $this->url_prefix = $dir;
  }

  /**
   * Add a dispatch rule to be processed.
   * The order rules are added is important.
   * Catchall rules should be added last, and to the very bottom!
   *
   * @param Array $rules   The rule specification to add.
   * @param Bool  $top     If set to True, add to the top.
   */
  public function addRoute ($rules, $top=false)
  {
    if ($top)
      array_unshift($this->controllers, $rules);
    else
      array_push($this->controllers, $rules);
  }

  /**
   * Add rules that map multiple routes to a single controller.
   * 
   * @param Array $baserules   The base rules used to build individual rules.
   * @param Array $methods     A list of methods the controller supports.
   */
  public function addRoutes ($baserules, $methods)
  {
    if (is_string($baserules))
    {
      $baserules = array('name'=>$baserules);
    }
    // If we have no path matching rules,
    // we'll generate a default.
    if (
         !isset($baserules['prefix']) 
      && !isset($baserules['matchpath'])
      && !isset($baserules['ispath'])
    )
    {
      $generate_default = True;
    }
    else
    {
      $generate_default = False;
    }

    foreach ($methods as $method)
    {
      $rules = $baserules;
      if ($method == 'default')
      {
        if (isset($rules['make_prefix']))
        {
          $rules['prefix'] = $rules['make_prefix'];
        }
        elseif (isset($rules['make_path']))
        {
          $rules['prefix'] = $rules['make_path'];
        }
        else
        {
          $rules['prefix'] = $rules['name'];
        }
      }
      else
      {
        if (isset($rules['make_prefix']))
        {
          // Build a prefix based on a common
          // prefix with the method appended to it.
          $rules['prefix'] = $rules['make_prefix'] . $method;
        }
        elseif (isset($rules['make_path']))
        {
          // Build ispath rules.
          $rules['ispath'] = array(
            $rules['make_path'],     // 0 = whatever is passed here.
            $method                  // 1 = the method name.
          );
        }
        elseif ($generate_default)
        {
          // If no other path matching rules are found,
          // let's generate one based on the method name.
          $rules['prefix'] = $method;
        }
      }
      $rules['method'] = "handle_$method";
      $this->addRoute($rules);
    }
  }

  /** 
   * Add a single controller that handles multiple routes
   * with a given prefix. Very simplistic.
   *
   * @param String $name     The name of the controller, and URL prefix.
   * @param Array  $handles  List of methods (see addRoutes() for details.)
   */
  public function addPrefixController ($name, $handles)
  {
    $rules = array
    (
      'name'      => $name,  // Controller is the same as the name.
      'make_path' => $name,  // Build a is_path rule using $name.
      'setpath'   => 2,      // 0 = name, 1 = method, 2 = variable!
      'defpath'   => False,  // No default path.
    );
    return $this->addRoutes($rules, $handles);
  }

  /** 
   * Add a single controller which uses the prefix as the controller name.
   *
   * @param String $name     The name of the controller and URL prefix.
   * @param Int    $offset   If set to an integer, defines path offset.
   */
  public function addSingleController ($name, $offset=False)
  {
    $rules = array
    (
      'name'      => $name,
      'prefix'    => $name,
      'defpath'   => False,
    );

    if (is_numeric($offset))
    {
      $rules['setpath'] = $offset;
    }

    return $this->addRoute($rules);
  }

  /** 
   * Add a controller to a nested path specification.
   *
   * Due to the way Dispatch matches rules, you must make sure
   * deeper nested path specs are added first, so they match before
   * their parent paths.
   *
   * @param Mixed   $paths       The path to match. See below.
   * @param String  $name        The controller name.
   * @param Int     $offset      If set to an integer, defines path offset.
   *
   * The $paths can be specified in two formats. One is a simple path
   * string, with / characters separating the path segments. The other
   * is to specify an array of path components. If using the array method,
   * you must ensure the path components are valid and contain no / characters.
   */    
  public function addNestedController ($paths, $name, $offset=False)
  {
    // We support a path string for easier use.
    if (!is_array($paths))
    {
      $paths = explode('/', trim($paths, "/ \n\r"));
    }

    $rules = array
    (
      'name'    => $name,
      'ispath'  => $paths,
      'defpath' => False,
    );
    if (is_numeric($offset))
    {
      $rules['setpath'] = $offset;
    }
    return $this->addRoute($rules);
  }

  /** 
   * Add CodeIgniter-style controller dispatching.
   *
   * Adds a controller rule that will determine the controller name
   * and method name based on the URL path. If you use this, it should
   * be added after any explicit URL matching rules.
   *
   * @param Bool $splice    Remove the first two path entries? [True]
   */
  public function addDynamicController ($splice=true)
  {
    $ctrl = array('cpath'=>0, 'mpath'=>1);
    if ($splice)
      $ctrl['cutpath'] = 2;
    $this->addRoute($ctrl);
  }

  /**
   * Add a default controller that will be used if no other rules match.
   *
   * This should be done only after ALL other rules have been added, as this
   * rule will always match, and therefore no rule after it will ever be
   * matched.
   *
   * @param String $name   Name of the controller to dispatch to.
   */
  public function addDefaultController ($name)
  {
    $ctrl = array('name'=>$name);
    $this->addRoute($ctrl);
  }

  /** 
   * Add a controller which will be used at the "root" URL.
   *
   * This matches the "base" or "root" URL only.
   * There can be only one "root" controller, and the first one
   * found will be dispatched to.
   *
   * @param String $name    Name of the controller to dispatch to.
   */
  public function addRootController($name)
  {
    $ctrl = array('name'=>$name, 'root'=>True);
    $this->addRoute($ctrl);
  }

  /**
   * Add a redirection rule as the "root" controller.
   *
   * This is mutually exclusive with addRootController().
   * As with any "root" controller, there can be only one.
   *
   * @param String $url   The URL to redirect the "root" path to.
   */
  public function addRootRedirect($url)
  {
    $ctrl = array('root'=>True, 'redirect'=>$url);
    $this->addRoute($ctrl);
  }

  /** 
   * Add a controller that see's if it can handle the path via a method.
   *
   * The lookup method can return a few different values:
   *
   *   - String:  the method name that handles the path.
   *   - True:    this path is handled, use the default method name.
   *   - False:   this path is not handled, this rule thus fails.  
   *
   * @param String $name    The name of the controller.
   * @param String $method  The name of the lookup method.
   */
  public function addLookupController ($name, $method)
  {
     $ctrl = array('name'=>$name, 'lookup'=>$method);
     $this->addRoute($ctrl);
  }

  /**
   * Dispatch to a controller based on the rules we have added.
   *
   * @param Bool $reverse   If set to True, process rules in reverse order.
   */
  public function dispatch ($reverse=false)
  { // A few things we need first.

    $nano = \Nano3\get_instance();

    $path  = get_path();
    if (isset($this->url_prefix))
    {
#      error_log("stripping {$this->url_prefix} from $path");
      $path = str_replace($this->url_prefix, '', $path);
    }
    $our_paths = get_routing($path);

#    error_log("Paths: ".json_encode($our_paths));


    if ($reverse)
      $controllers = array_reverse($this->controllers);
    else
      $controllers = $this->controllers;

    foreach ($controllers as $ctrl)
    { // Set a few variables that may be used later.
      $matches    = array(); // Used for storing matches.
      $controller = null;    // This must be set, otherwise we will fail.
      $method     = $this->default_method;
      $paths      = $our_paths;

#      error_log("Ctrl: ".json_encode($ctrl));

      if (isset($ctrl['root']) && $ctrl['root'])
      { // This only matches if $path is '/' or ''.
        if ($path != '/' && $path != '')
          continue;
      }
      if (isset($ctrl['matchpath']))
      { // Check for regular expression matches against the full path string.
        $match = $ctrl['matchpath'];
        if (preg_match($match, $path, $matches)==0) // didn't match.
          continue;
      }
      elseif (isset($ctrl['prefix']))
      { // See if the first component of the path matches a string value.
#        error_log("Seeing if {$paths[0]} is {$ctrl['prefix']}");
        if (count($paths)==0 || $paths[0] != $ctrl['prefix'])
          continue;
#        error_log(" -- we made it past there.");
      }
      if (isset($ctrl['ispath']))
      {
#        error_log("--- ispath test ---");
        $pcount = count($paths);
        if ($pcount==0) continue; // We need at least one path.
        if ($pcount < count($ctrl['ispath'])) continue; // Too small.
        $failed = False;
        foreach ($ctrl['ispath'] as $part => $match)
        {
          if ($paths[$part] != $match)
          {
            $failed = True;
            break;
          }
        }
        if ($failed) continue;
      }

      if (isset($ctrl['redirect']))
      {
        $nano->url->redirect($ctrl['redirect']);
      }

      // Now try to find the controller name.
      if (isset($ctrl['name']))
        $controller = $ctrl['name'];
      elseif (isset($ctrl['cmatch']) && count($matches)>=$ctrl['cmatch']+1)
        $controller = $matches[$ctrl['cmatch']];
      elseif (isset($ctrl['cpath']) && count($paths)>=$ctrl['cpath']+1)
        $controller = $paths[$ctrl['cpath']];
      // Now try to find the method name.
      if (isset($ctrl['method']))
        $method = $ctrl['method'];
      elseif (isset($ctrl['mmatch']) && count($matches)>=$ctrl['mmatch']+1)
        $method = "handle_".$matches[$ctrl['mmatch']];
      elseif (isset($ctrl['mpath']) && count($paths)>=$ctrl['mpath']+1)
        $method = "handle_".$paths[$ctrl['mpath']];

#      error_log("Okay, after tests, looking for '$controller'");

      // Okay, now let's ensure the controller is valid.
      if (!isset($controller)) continue;
      if (!$nano->controllers->is($controller)) continue;

#      error_log(" --- going to dispatch: '$controller'");

      // Load the controller using the magic from the controllers extension.
      $controller = $nano->controllers->load($controller);

      // If we define a lookup rule, then we look up if we can handle the
      // path based on the method name in the lookup rule.
      //
      // If the lookup method returns False or Null, it is skipped.
      // If the method returns True as a boolean value, we continue.
      // If the method returns a String, we make that our method name,
      // and continue.
      if (isset($ctrl['lookup']))
      { $lookup = $ctrl['lookup'];
        if (!is_callable(array($controller, $lookup))) continue;
        $can_handle = $ctrl->$lookup($path);
        if (is_string($can_handle))
        {
          $method = $can_handle;
        }
        else
        {
          if (!$can_handle) continue;
        }
      }

      // Okay, now let's ensure the method is valid.
      if (!isset($method)) continue;
      if (!is_callable(array($controller, $method))) continue;

      // Okay, now let's figure out what all data we need to send to the
      // controller. Typically we send the $_REQUEST and $paths, but this
      // can be overridden.
      $data = $_REQUEST;
      if (isset($ctrl['data']))
      { if (is_array($ctrl['data']))
          $data = $ctrl['data'];
        elseif ($ctrl['data'] == 'GET')
          $data = $_GET;
        elseif ($ctrl['data'] == 'POST')
          $data = $_POST;
      }

      if (isset($ctrl['paths']))
        $paths = $ctrl['paths'];
      elseif (isset($ctrl['setpath']))
      { // We only want one of the paths.
#        error_log("looking for section ".$ctrl['setpath']." in ".json_encode($paths));
        if (count($paths)>=$ctrl['setpath']+1)  // Use a specific path bit.
          $paths = $paths[$ctrl['setpath']];
        elseif (isset($ctrl['defpath']))        // Use a specified default.
          $paths = $ctrl['defpath'];
        else
          $paths = null;                        // Use nothing, sorry.
      }
      elseif (isset($ctrl['cutpath']))
      { // Let's remove selected elements from the paths array.
        if (is_array($ctrl['cutpath']))
          array_splice($paths, $ctrl['cutpath'][0], $ctrl['cutpath'][1]);
        else
          array_splice($paths, 0, $ctrl['cutpath']);
      }

#      error_log("dispatching with data: ".json_encode($data));

      // Finally, if we've made it this far, let's process the controller
      // and return the output.
      $output = $controller->$method($data, $paths);

      return $output;

    }

    // If we made it here, it means we found no valid controller.
    // This is a Bad Thing (C).
    // We throw an exception, and let your app catch it.
    throw new Exception("No valid controller found.");

  }

}

// End of library.

