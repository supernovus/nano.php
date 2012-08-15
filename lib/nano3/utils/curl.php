<?php

namespace Nano3\Utils;
use Nano3\Exception;

/**
 * An object-oriented wrapper for the PHP cURL library.
 * TODO: file uploads.
 */
class Curl
{
  public    $strict = False;    // If true, get() will die on failure.
  protected $curl;              // The current cURL object, created by init().
  protected $curl_opts;         // Options to initialize the cURL object.

  // The following is a map of friendly option names to curl option integers.
  protected $curl_map = array
  (
    'binary'     => CURLOPT_BINARYTRANSFER,
    'encoding'   => CURLOPT_ENCODING,
    'follow'     => CURLOPT_FOLLOWLOCATION,
    'fresh'      => CURLOPT_FRESH_CONNECT,
    'timeout'    => CURLOPT_TIMEOUT,
    'connect'    => CURLOPT_CONNECTTIMEOUT,
    'post'       => CURLOPT_POST,
    'postdata'   => CURLOPT_POSTFIELDS,
    'authinfo'   => CURLOPT_USERPWD,
    'upload'     => CURLOPT_UPLOAD,
    'autoref'    => CURLOPT_AUTOREFERER,
    'method'     => CURLOPT_CUSTOMREQUEST,
  );

  // The following is a map of friendly names for curl info integers.
  // TODO: add more here.
  protected $curl_info = array
  (
    'code'     => CURLINFO_HTTP_CODE,
  );

  public function __construct ($opts=array())
  {
    // The default options. These are not overridable.
    $this->curl_opts = array(
      CURLOPT_RETURNTRANSFER => True,
      CURLOPT_SSL_VERIFYHOST => False,
      CURLOPT_SSL_VERIFYPEER => False,
    );
    foreach ($this->curl_map as $key => $val)
    {
      if (isset($opts[$key]))
      {
        $this->curl_opts[$val] = $opts[$key];
      }
    }
  }

  protected function get_map ($name)
  {
    if (isset($this->curl_map[$name]))
    {
      return $this->curl_map[$name];
    }
    else
    {
      throw new Exception('Unknown option.');
    }
  }

  public function __get ($name)
  {
    $opt = $this->get_map($name);
    if (isset($this->curl_opts[$opt]))
    {
      return $this->curl_opts[$opt];
    }
  }

  public function __set ($name, $value)
  {
    $opt = $this->get_map($name);
    $this->curl_opts[$opt] = $value;
  }

  public function __unset ($name)
  {
    $opt = $this->get_map($name);
    unset($this->curl_opts[$opt]);
  }

  public function __isset ($name)
  {
    $opt = $this->get_map($name);
    return isset($this->curl_opts[$opt]);
  }

  public function init ()
  {
    if (isset($this->curl))
    {
      $this->end();
    }
    $this->curl = curl_init();
    curl_setopt_array($this->curl, $this->curl_opts);
  }

  public function end ()
  {
    curl_close($this->curl);
    $this->curl = Null;
  }

  // The request function does not close the cURL session,
  // and returns its output directly. If you want easy wrappers,
  // use the get() or post() methods.
  public function request ($url)
  {
    if (!isset($this->curl))
    {
      $this->init();
    }
    curl_setopt($this->curl, CURLOPT_URL, $url);
    return curl_exec($this->curl);
  }

  public function getError ()
  {
    if (isset($this->curl))
    {
      return curl_error($curl);
    }
  }

  public function getInfo ($info='code')
  {
    if (isset($this->curl) && isset($this->curl_info[$info]))
    {
      $what = $this->curl_info[$info];
      return curl_info($curl, $what);
    }
  }

  public function get ($url)
  {
    $this->init();
    $output = $this->request($url);
    if ($output === False || $output == '')
    {
      $output = array(
        'error' => $this->getError(),
        'code'  => $this->getInfo(),
      );
      if ($this->strict)
      {
        throw new Exception('cURL request failed: '.json_encode($output));
      }
    }
    $this->end();
    return $output;
  }

  public function post ($url, $postdata=Null)
  {
    $this->post = True;
    if (isset($postdata))
    {
      $this->postdata = $postdata;
    }
    return $this->get($url);
  }

}
