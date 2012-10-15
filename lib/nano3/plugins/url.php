<?php

/**
 * Common URL functions.
 *
 * Can be used as static class methods, or as an object.
 */

namespace Nano3\Plugins;

class URL
{
  /** 
   * Redirect to another page. This ends the current PHP process.
   *
   * @param String $url    The URL to redirect to.
   * @param Array  $opts   Options:
   *
   *   'relative'         If True, the passed URL is actually a path.
   *   'full'             If True, the passed URL is a full URL.
   *
   * The above two options are mutually exclusive and do the exact opposite.
   * If neither option is set, the URL will be checked for the existence
   * of a colon (':') character, which will determine if it is assumed to be
   * a full URL or a relative URL.
   *
   * If relative is determiend to be true, the following options are added:
   *
   *   'ssl'              If True, force the use of SSL on the site URL.
   *   'port'             If set, use this port on the site URL.
   *
   * See the site_url() function for details on how 'ssl' and 'port' work.
   *
   */
  public static function redirect ($url, $opts=array())
  {
    // Set if we set an explicit 'relative' or 'full' option.
    if (isset($opts['relative']))
    {
      $relative = $opts['relative'];
    }
    elseif (isset($opts['full']))
    {
      $relative = $opts['full'] ? False : True;
    }
    else
    {
      // No relative or full option set, determine based on passed URL/path.
      $relative = is_numeric(strpos($url, ':')) ? False : True;
    }

    if ($relative)
    {
      // Determine if we should force SSL.
      if (isset($opts['ssl']))
        $ssl = $opts['ssl'];
      else
        $ssl = Null;

      // Determine if we should use an alternative port.
      if (isset($opts['port']))
        $port = $opts['port'];
      else
        $port = Null; // Use the default ports.

      // Prepend the site URL to the passed path.
      $url = static::site_url($ssl, $port) . $url;
    }

    // Spit out a 'Location' header, and end the PHP process.
    header("Location: $url");
    exit;
  }

  /** 
   * Return our website's base URL.
   *
   * @param Mixed $ssl      Force the use of SSL?
   * @param Mixed $port     Force a specific port?
   *
   * If $ssl is True, we force SSL, if it is False, we force regular HTTP.
   * If it is Null (default) we auto-detect the current protocol and use that.
   *
   * If $port is set to an integer, we force that as the port.
   * If it is Null (default) we determine the appropriate port to use.
   */
  public static function site_url ($ssl=Null, $port=Null)
  { 
    if (isset($port))
    {
      if (is_numeric($port))
      {
        $port = ':' . $port;
      }
    }
    if (isset($ssl))
    { // We're using explicit SSL settings.
      if ($ssl)
      {
        $proto   = 'https';
      }
      else
      {
        $proto   = 'http';
      }
      if (is_null($port))
      {
        $port = ''; // Force the use of the default port.
      }
    }
    else
    { // Auto-detect SSL and port settings.
      if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
      { 
        $defport = 443;
        $proto   = 'https';
      }
      else
      { 
        $defport = 80;
        $proto   = 'http';
      }
      if (is_null($port))
      { // Is our current port a default port or not?
        $port = ($_SERVER['SERVER_PORT'] == $defport) 
          ? '' 
          : (':' . $_SERVER['SERVER_PORT']);
      }
    }
    return $proto.'://'.$_SERVER['SERVER_NAME'].$port;
  }

  /** 
   * Return our current request URI.
   */
  public static function request_uri ()
  {
    if (isset($_SERVER['REQUEST_URI']))
    {
      return $_SERVER['REQUEST_URI'];
    }
    else
    {
      $uri = $_SERVER['SCRIPT_NAME'];
      if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] != '')
      {
        $uri .= '/' . $_SERVER['PATH_INFO'];
      }
      if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '')
      {
        $uri .= '?' . $_SERVER['QUERY_STRING'];
      }
      $uri = '/' . ltrim($uri, '/');

      return $uri;
    }
  }

  /** 
   * Return the current URL (full URL path)
   */
  public function current_url ()
  {
    return static::site_url() . static::request_uri();
  }

  /** 
   * Return the name of the current script.
   *
   * @param Bool $full   If set to True, return the full path.
   */
  public function script_name ($full=False)
  {
    if ($full)
    {
      return $_SERVER['SCRIPT_NAME'];
    }
    return basename($_SERVER['SCRIPT_NAME']);
  }

  /** 
   * Send a file download to the client browser.
   * This ends the current PHP process.
   *
   * @param Mixed $file    See below for possibly values.
   * @param Array $opts    See below for a list of options.
   *
   * The $file variable will be one of two values, depending on
   * options. If the 'content' option exists, the $file variable will
   * be used as the filename for the download. Otherwise, the $file variable
   * is the path name on the server to the file we are sending the client.
   *
   * Options:
   *
   *   'type'      If specified, will be used as the MIME type, see below.
   *   'content'   Use this as the file content, see below.
   *   'filename'  If not using content, this sets the filename, see below.
   *
   * The 'type' option if set determines the MIME type. If there is a slash
   * character, it is assumed to be a full MIME type declaration. If there
   * isn't, we assume it's a query for Nano3\Utils\File\Types, and look up the
   * MIME type from there. If it isn't specified at all, we use finfo to look
   * up the MIME type based on the file content.
   *
   * If the 'content' option is set, then it will be used as the content of
   * the file. It alters the meaning of the $file parameter as mentioned above.
   * If it is not set, then the $file parameter must point to a valid file on
   * the server.
   *
   * If the 'filename' option is set (only valid if 'content' is not set),
   * then its value will be used as the name of the file being sent to the
   * client. If it is not set, then the basename of the existing file on the
   * server will be used (as specified in the $file parameter.)
   *
   */
  public static function download ($file, $opts=array())
  {
    // First off, get the file type. We have a few common aliases
    // available, which may be faster than using finfo?
    if (isset($opts['type']))
    {
      $type = $opts['type'];
      if (! is_numeric(strpos($type, '/')))
      {
        $detectType = \Nano3\Utils\File\Types::get($type);
        if (isset($detectType))
        {
          $type = $detectType;
        }
      }
    }
    elseif (isset($opts['content']))
    { // We have explicit content.
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $type = $finfo->buffer($opts['content']);
    }
    else
    { // We're reading from a file.
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $type  = $finfo->file($file);
    }

    // Next, get the filename and filesize.
    if (isset($opts['content']))
    {
      $filename = $file; // Assume they are the same thing.
      $filesize = strlen($opts['content']);
    }
    else
    {
      if (isset($opts['filename']))
        $filename = $opts['filename'];
      else
        $filename = basename($file); // Chop the directory portion.
      $filesize = filesize($file);   // Get the filesize.
    }

    header('Content-Description: File Transfer');
    header("Content-Type: $type;" . 'name="' . $filename . '"');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header("Content-Length: $filesize");
    ob_clean();
    flush();
    if (isset($opts['content']))
    {
      echo $opts['content'];
    }
    else
    {
      readfile($file);
    }
    // Now leaving PHP-land.
    exit;
  }

  /**
   * Transform a PHP array/object into a URL-safe string.
   *
   * @param Mixed $input        The PHP array/object to encode.
   * @param Bool  $serialize    If set to True, we use serialize().
   *
   * The format of the string is simple:
   *
   * We encode the input, then we Base64 encode the data string. 
   * Finally we perform the following character substitutions:
   *
   *   '+' becomes '-'
   *   '/' becomes '_'
   *   '=' becomes '.'
   *
   * The data encoding scheme depends on the $serialize parameter:
   *
   * If $serialize is False (default) then we use JSON encoding, in which
   * case the input MUST be a PHP Array, and all members of the PHP array 
   * MUST be able to be handled by the json_encode() function.
   *
   * If $serialize is True, we use the serialize() function.
   * This allows for more types of objects to be encoded, but also means that
   * you will only be able to use the URL string with PHP.
   */
  public static function encodeArray ($object, $serialize=False)
  {
    if ($serialize)
    {
      $encoded = serialize($object);
    }
    else
    {
      $encoded = json_encode($object);
    }
    return strtr(base64_encode($encoded), '+/=', '-_.');
  }

  /** 
   * Decode a string in encodeArray() format.
   *
   * @param String $input        The URL string to decode.
   * @param Bool   $serialize    Did the input string use serialize?
   * @param Bool   $assoc        JSON objects will become PHP arrays (True)
   *
   * The $serialize parameter MUST reflect the same value as was used in
   * the encodeArray() call that generated the original string. 
   * It defaults to False (the same default as encodeArray().)
   *
   * The $assoc parameter (default True) is only used by the JSON decoder,
   * and is passed along to the json_decode() function to determine if JSON
   * Objects will be decoded as PHP Objects or PHP Arrays.
   */
  public static function decodeArray ($string, $serialize=False, $assoc=True)
  {
    $decoded = base64_decode(strtr($string, '-_.', '+/=');
    if ($serialize)
    {
      return unserialize($decoded);
    }
    else
    {
      return json_decode($decoded), $assoc);
    }
  }

}
