<?php

/**
 * Common URL functions.
 *
 * Can be used as static class methods, or as an object.
 *
 */

namespace Nano3\Plugins;

class URL
{
  // Redirect to another page. This ends the current PHP process.
  public function redirect ($url, $opts=array())
  { // Check for some options. 'relative'=>False or 'full'=>True are the same.
    if (isset($opts['relative']))
      $relative = $opts['relative'];
    elseif (isset($opts['full']) && $opts['full'])
      $relative = False;
    else
      $relative = True; // Assume true by default.
    if (isset($opts['secure']))
      $ssl = $opts['secure'];
    elseif (isset($opts['ssl']))
      $ssl = $opts['ssl'];
    else
      $ssl = Null; // Auto determine the protocol.
    if (isset($opts['port']))
      $port = $opts['port'];
    else
      $port = ''; // Use the default ports.

    if ($relative)
    {
      $class = __CLASS__;
      $url = $class::site_url($ssl, $port) . $url;
    }

    header("Location: $url");
    exit;
  }

  // Return our website's base URL.
  // We can force the use of SSL, or alternative ports.
  public function site_url ($ssl=Null, $port='')
  { if (isset($ssl))
    { // We're using explicit SSL settings.
      if ($ssl)
      {
        $defport = 443;
        $proto   = "https";
      }
      else
      {
        $defport = 80;
        $proto   = "http";
      }
    }
    else
    { // Auto-detect SSL and port settings.
      if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on")
      { 
        $defport = 443;
        $proto   = "https";
      }
      else
      { 
        $defport = 80;
        $proto   = "http";
      }
      $port = ($_SERVER["SERVER_PORT"] == $defport) ? '' : 
        (":".$_SERVER["SERVER_PORT"]);
    }
    return $proto."://".$_SERVER['SERVER_NAME'].$port;
  }

  // Return our current request URI.
  public function request_uri ()
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

  // Return the current URL (full URL path)
  public function current_url ()
  {
    $class = __CLASS__;
    $full_url = $class::site_url() . $class::request_uri();
    return $full_url;
  }

  // Return the name of the current script.
  public function script_name ($full=False)
  {
    if ($full)
    {
      return $_SERVER['SCRIPT_NAME'];
    }
    return basename($_SERVER['SCRIPT_NAME']);
  }

  // Download a file.
  public function download ($file, $opts=array())
  {
    // First off, get the file type. We have a few common aliases
    // available, which may be faster than using finfo?
    if (isset($opts['type']))
    {
      $typealiases = array(
        'xml'  => 'text/xml',
        'html' => 'text/html',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // TODO: add more.
      );
      if (isset($typealiases[$type]))
      {
        $type = $typealiases[$type];
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
      $filename = basename($file); // Chop the directory portion.
      $filesize = filesize($file); // Get the filesize.
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

}
