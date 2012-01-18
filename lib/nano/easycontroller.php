<?php

/* EasyController: An extention to CoreController that offers more features.
   This has display(), redirect() and url() methods, as well as a $data
   protected member for storing the replacement data in.
 */

load_core('controller');

abstract class EasyController extends CoreController
{
  protected $data;         // Our data to send to the templates.
  protected $default_url;  // Where redirect() goes if no URL is specified.

  public function display ($data=null, $screen=null)
  {
    if (isset($data) && is_array($data))
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data += $this->data;
      }
    }
    else
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data = $this->data;
      }
      else
      {
        $data = array();
      }
    }
    if (is_null($screen))
      $screen = $this->name();
    return $this->process_template($screen, $data);
  }

  // Redirect to another page. This ends the current PHP process.
  public function redirect ($url=null, $relative=true)
  {
    if (is_null($url))
      $url = $this->default_url;
    if ($relative)
    {
      $url = $this->url() . $url;
    }
    header("Location: $url");
    exit;
  }

  public function url ()
  {
    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on")
    { $defport = "443";
      $proto = "https";
    }
    else
    { $defport = "80";
      $proto = "http";
    }
    $port = ($_SERVER["SERVER_PORT"] == $defport) ? '' : (":".$_SERVER["SERVER_PORT"]);
    return $proto."://".$_SERVER['SERVER_NAME'].$port;
  }

}
