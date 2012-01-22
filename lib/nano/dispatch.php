<?php

/* Controller Dispatch for Nano.
   Supports a broad array of rules to dispatch using.
   We no longer support the CodeIgniter implicit dispatching directly.
   You can simulate it using rules though.
 */

load_core('controllers');

class DispatchException extends Exception {}

// A list of known controllers.
global $__nano_controllers;
$__nano_controllers = array();
// The default method to call on the controllers if none is specified.
global $__nano_default_controller_method;
$__nano_default_controller_method = 'handle_dispatch';

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

## Get the first item in the paths array,
## removing it from the array, otherwise,
## return the default value.
function get_first_from_array (&$array, $default="default")
{
  $count = count($array);
  if ($count>0)
    $value = array_shift($array);
  else
    $value = $default;
  return $value;
}

## Add a controller to the explicit controllers list.
## Catchall rules should be added last.
function add_controller ($rules, $top=false)
{
  global $__nano_controllers;
  if ($top)
    array_unshift($__nano_controllers, $rules);
  else
    array_push($__nano_controllers, $rules);
}

## Add a CodeIgniter-style controller
function add_dynamic_controller ($splice=true)
{
  $ctrl = array('cpath'=>0, 'mpath'=>1);
  if ($splice)
    $ctrl['cutpath'] = 2;
  add_controller($ctrl);
}

## Add a default controller with a given name.
function add_default_controller ($name='default')
{
  $ctrl = array('name'=>$name);
  add_controller($ctrl);
}

## Find a controller based on the path.
## At a bare minimum, expects that there is a controller called default,
## and a handle_dispatch() method in each controller.
function dispatch_controller ($reverse=false)
{ // A few things we need first.
  global $__nano_controller_dir;
  global $__nano_controllers;
  global $__nano_default_controller_method;

  $path  = get_path();
  $our_paths = get_routing($path);

  if ($reverse)
    $controllers = array_reverse($__nano_controllers);
  else
    $controllers = $__nano_controllers;

  foreach ($controllers as $ctrl)
  { // Set a few variables that may be used later.
    $matches    = array(); // Used for storing matches.
    $controller = null;    // This must be set, otherwise we will fail.
    $method     = $__nano_default_controller_method;
    $paths      = $our_paths;

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
      if (count($paths)==0 || $paths[0] != $ctrl['prefix'])
        continue;
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

#    error_log("Okay, after tests, looking for '$controller'");

    // Okay, now let's ensure the controller is valid.
    if (!isset($controller)) continue;
    if (!file_exists("$__nano_controller_dir/$controller.php")) continue;

#    error_log("going to dispatch: '$controller'");

    // Load the controller using the magic from the controllers extension.
    $controller = load_controller($controller);

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

#    error_log("dispatching with data: ".json_encode($data));

    // Finally, if we've made it this far, let's process the controller
    // and return the output.
    $output = $controller->$method($data, $paths);

    return $output;

  }

  // If we made it here, it means we found no valid controller.
  // This is a Bad Thing (C).
  // We throw an exception, and let your app catch it.
  throw new DispatchException("No valid controller found.");

}

