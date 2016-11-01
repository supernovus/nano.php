<?php

namespace Nano\Plugins;

/**
 * Nano Session Object
 *
 * @package Nano\Sess
 * @author  Timothy Totten
 */
class Sess implements \ArrayAccess
{
  public function __construct ($opts=array())
  {
    if (isset($opts['id']))
    {
      session_id($opts['id']);
    }
    if (isset($opts['name']))
    {
      session_name($opts['name']);
    }
    if (!isset($opts['no_start']))
    {
      @session_start();
    }
  }

  public function offsetSet ($id, $value)
  {
    $_SESSION[$id] = $value;
  }

  public function offsetGet ($id)
  {
    return $_SESSION[$id];
  }

  public function offsetExists ($id)
  {
    return isset($_SESSION[$id]);
  }

  public function offsetUnset($id)
  {
    unset($_SESSION[$id]);
  }

  public function __set ($id, $value)
  {
    $this->offsetSet($id, $value);
  }

  public function __get ($id)
  {
    return $this->offsetGet($id);
  }

  public function __isset ($id)
  {
    return $this->offsetExists($id);
  }

  public function __unset ($id)
  {
    $this->offsetUnset($id);
  }

  public function id ()
  {
    return session_id();
  }

  public function name ()
  {
    return session_name();
  }

  public function start ()
  {
    return session_start();
  }

  public function kill ($restart=False)
  {
    $_SESSION = array();
    if (ini_get("session.use_cookies"))
    {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 4200,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    $destroyed = session_destroy();
    if ($restart)
    {
      $this->start();
    }
    return $destroyed;
  }

}

// End of library.
