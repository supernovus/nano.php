<?php

/* Controller Dispatch for Nano.
   Supports a broad array of rules to dispatch using.
   We no longer support the CodeIgniter implicit dispatching directly.
   You can simulate it using rules though.
 */

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

// Return the path info.
function get_path ()
{ // Extracted from get_routing for use elsewhere.
  #$path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO');
  $path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : $_SERVER['REQUEST_URI'];
  $path = remove_invisible_characters($path, FALSE);
#  error_log("found path: $path");
  return $path;
}

// Look up the path the user went to, and expand it into an array.
// Based on code from CodeIgniter, but greatly simplified.
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

// A base class to implement dispatch rules.
class Dispatch
{
  protected $controllers = array();                // Known controllers.
  protected $default_method = 'handle_dispatch';   // Default method to call.

  public $url_prefix;                              // Use if in subdir.

  // A quick method to set the url_prefix.
  public function url_prefix ($dir=Null)
  {
    if (is_null($dir))
    {
      $dir = dirname($_SERVER['SCRIPT_NAME']);
    }
    $this->url_prefix = $dir;
  }

  // Add a controller to the explicit controllers list.
  // Catchall rules should be added last.
  public function addRoute ($rules, $top=false)
  {
    if ($top)
      array_unshift($this->controllers, $rules);
    else
      array_push($this->controllers, $rules);
  }

  // Add a single controller that handles multiple routes.
  // NOTE: pre-existing prefix rule will be appended to
  // the method for the prefix to look for.
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

  // Add a single controller that handles multiple routes
  // with a given prefix. A simple wrapper to addRoutes().
  // This assumes a lot, so if you need something more advanced,
  // write your own addRoutes() call.
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

  // Add a single controller which uses the prefix as the controller name.
  // Like addPrefixController this assumes a lot.
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

  // Add a CodeIgniter-style controller.
  public function addDynamicController ($splice=true)
  {
    $ctrl = array('cpath'=>0, 'mpath'=>1);
    if ($splice)
      $ctrl['cutpath'] = 2;
    $this->addRoute($ctrl);
  }

  // Add a default controller with a given name.
  // Unlike previous versions, this does not have a default name.
  public function addDefaultController ($name)
  {
    $ctrl = array('name'=>$name);
    $this->addRoute($ctrl);
  }

  // Add a root controller with a given name.
  public function addRootController($name)
  {
    $ctrl = array('name'=>$name, 'root'=>True);
    $this->addRoute($ctrl);
  }

  // Add a controller that see's if it can handle the path.
  public function addLookupController ($name, $method)
  {
     $ctrl = array('name'=>$name, 'lookup'=>$method);
     $this->addRoute($ctrl);
  }

  // Find a controller based on the path.
  // At a bare minimum, expects that there is a controller called default,
  // and a handle_dispatch() method in each controller.
  function dispatch ($reverse=false)
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

      // Okay, now let's ensure the method is valid.
      if (!isset($method)) continue;
      if (!is_callable(array($controller, $method))) continue;

      // Now, one last filter rule, for controllers that have their
      // own method of determining if they handle a path.
      if (isset($ctrl['lookup']))
      { $lookup = $ctrl['lookup'];
      	if (!is_callable(array($controller, $lookup))) continue;
        if (!$method->$lookup($path)) continue;
      }

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

