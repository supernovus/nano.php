<?php

/* 
 * SimpleAuth: An object to handle authentication via simple user-hashes.
 *
 * An extremely basic user authentication system using a simple
 * hash-based approach. It has no specifications as to the user model,
 * other than it needs to store the hash provided by the generate_hash
 * method.
 *
 * It has an optional paranoid mode, which forces the use of the hash
 * in the is_user() check to ensure the user is valid.
 *
 */

namespace Nano3\Utils;

class SimpleAuth
{
  public $log = False;          // Enable logging?
  protected $store_self = True; // Store the object in the session.

  // Internal fields.
  protected $userid;
  protected $authhash;

  // Build a new object.
  public function __construct ($opts=array())
  {
    if (isset($opts['log']))
    {
      $this->log = $opts['log'];
    }

    if (isset($opts['store']))
    {
      $this->store_self = $opts['store'];
    }

    if (isset($opts['restore']) && $opts['restore'])
    {
      return $this->restore_session();
    }

    $nano = \Nano3\get_instance();

    if ($this->store_self)
    {
      $nano->sess->SimpleAuth = $this;
    }
    else
    {
      $nano->sess->SimpleAuth = array();
      $nano->sess->SimpleAuth['log']   = $this->log;
      $nano->sess->SimpleAuth['store'] = $this->store_self;
    }
  }

  // Static function to get an existing instance or create a new one.
  public static function getInstance ($opts=array())
  {
    $nano = \Nano3\get_instance();
    if (isset($nano->sess->SimpleAuth))
    {
      if (is_object($nano->sess->SimpleAuth))
      {
        return $nano->sess->SimpleAuth;
      }
      elseif (is_array($nano->sess->SimpleAuth))
      {
        $opts['restore'] = True;
        return new SimpleAuth($opts);
      }
    }
    return new SimpleAuth($opts);
  }

  // If we're not using store_self, we store
  // our variables in an array instead.
  private function restore_session ()
  { // Restore our settings from the array.
    $nano = \Nano3\get_instance();
    $this->log = $nano->sess->SimpleAuth['log'];
    $this->store_self = $nano->sess->SimpleAuth['store'];
    if (isset($nano->sess->SimpleAuth['userid']))
    {
      $this->userid = $nano->sess->SimpleAuth['userid'];
    }
    if (isset($nano->sess->SimpleAuth['hash']))
    {
      $this->authhash = $nano->sess->SimpleAuth['hash'];
    }
  }

  private function set_userid ($userid)
  {
    $this->userid = $userid;
    if (!$this->store_self)
    {
      $nano = \Nano3\get_instance();
      $nano->sess->SimpleAuth['userid'] = $userid;
    }
  }

  private function set_authhash ($hash)
  {
    $this->authhash = $hash;
    if (!$this->store_self)
    {
      $nano = \Nano3\get_instance();
      $nano->sess->SimpleAuth['hash'] = $hash;
    }
  }

  // Generate a password hash from a username and password.
  public function generate_hash ($token, $pass)
  {
    return sha1(trim($token.$pass));
  }

  // Returns if a user is logged in or not.
  // If a user is logged in, it returns their user id.
  // Otherwise, it returns false.
  public function is_user ($userhash=Null, $authtoken=Null)
  { 
    if (isset($this->userid))
    {
      if (isset($userhash) && isset($authtoken) && isset($this->authhash))
      { 
        $checkhash = sha1($authtoken.$userhash);
        if (strcmp($checkhash, $this->authhash) != 0)
        {
          return False;
        }
      }
      return $this->userid;
    }
    return False;
  }

  // Process a login request.
  public function login 
    ($userid, $pass, $userhash, $usertoken=Null, $paranoid=False)
  {
    // If we don't specify a user token, assume the same as userid.
    if (is_null($usertoken))
      $usertoken = $userid;

    $checkhash = $this->generate_hash($usertoken, $pass);
    if (strcmp($userhash, $checkhash) == 0)
    {
      $this->set_userid($userid);
      if ($this->log) error_log("User '$userid' logged in.");
      if ($paranoid)
      {
        $authtoken = sha1(time());
        $this->set_authhash(sha1($authtoken.$userhash));
        return $authtoken;
      }
      return true;
    }
    return false;
  }

  // Process a logout request.
  public function logout ($destroy_session=False, $restart_session=False)
  {
    if ($this->log)
    {
      $userid = $this->userid;
      error_log("User '$userid' logged out.");
    }
    $this->userid    = Null;
    $this->authhash  = Null;
    $nano = \Nano3\get_instance();
    if (!$this->store_self)
    {
      $nano->sess->SimpleAuth = array();
    }
    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $nano->sess->kill($restart_session);
    }
  }

}

// End of class.
