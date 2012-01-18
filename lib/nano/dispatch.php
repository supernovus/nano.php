<?php
/* CodeIgniter-like dispatch for Nano. */

load_core('controllers');

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
function get_routing () 
{ // We use the PATH_INFO to determine our routing.
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

## Find a controller based on the path.
## At a bare minimum, expects that there is a controller called default,
## and a handle_default() method in each controller.
function dispatch_controller ()
{ // A few things we need first.
  global $__nano_controller_dir;
  $paths = get_routing();

  $want_controller = get_first_from_array($paths);
  $want_method     = get_first_from_array($paths);

  if (!file_exists("$__nano_controller_dir/$want_controller.php"))
    $want_controller = "default";
  $controller = load_controller($want_controller);

  $method = "handle_$want_method";
  if (!method_exists($controller, $method))
    $method = "handle_default";

  ## Okay, now let's dispatch, and get the output.
  $output = $controller->$method($_REQUEST, $paths);

  return $output;
}

