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
  public $log = false;   // Enable logging?

  // Fields representing a logged in user.
  protected $userid;
  protected $usertoken;

  // Two fields for paranoid mode.
  protected $authtoken;
  protected $authhash;

  public function __construct ($opts=array())
  {
    if (isset($opts['log']))
      $this->log = $opts['log'];
    $nano = \Nano3\get_instance();
    $nano->sess->SimpleAuth = $this;
  }

  // Static function to get an existing instance or create a new one.
  public static function getInstance ()
  {
    $nano = \Nano3\get_instance();
    if (isset($nano->sess->SimpleAuth))
    {
      return $nano->sess->SimpleAuth;
    }
    else
    {
      return new SimpleAuth();
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
      if (isset($userhash) && isset($this->authhash))
      { 
        if (!isset($token))
        { // Use our own token.
          $authtoken = $this->authtoken;
        }
        $checkhash = sha1($this->userid.$authtoken.$userhash);
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
      $this->userid = $userid;
      $this->usertoken = $usertoken;
      if ($this->log) error_log("User '$userid' logged in.");
      if ($paranoid)
      {
        $this->authtoken = time();
        $this->authhash = sha1($userid.$this->authtoken.$userhash);
        return $this->authtoken;
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
    $this->usertoken = Null;
    $this->authtoken = Null;
    $this->authhash  = Null;
    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $nano = \Nano3\get_instance();
      $nano->sess->kill($restart_session);
    }
  }

}

// End of class.
