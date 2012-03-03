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
  protected $user;

  // Two fields for paranoid mode.
  protected $token;
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
  public function generate_hash ($user, $pass)
  {
    return sha1(trim($user.$pass));
  }

  // Returns if a user is logged in or not.
  // If a user is logged in, it returns their user id.
  // Otherwise, it returns false.
  public function is_user ($userhash=Null, $token=Null)
  { 
    if (isset($this->user))
    {
      $user = $this->user;
      if (isset($userhash) && isset($this->authhash))
      { 
        if (!isset($token))
        { // Use our own token.
          $token = $this->token;
        }
        $checkhash = sha1($user.$token.$userhash);
        if (strcmp($checkhash, $this->authhash) != 0)
        {
          return False;
        }
      }
      return $user;
    }
    return False;
  }

  // Process a login request.
  public function login ($user, $pass, $userhash, $paranoid=False)
  {
    $checkhash = $this->generate_hash($user, $pass);
    if (strcmp($userhash, $checkhash) == 0)
    {
      $this->user = $user;
      if ($this->log) error_log("User '$user' logged in.");
      if ($paranoid)
      {
        $this->token = time();
        $this->authhash = sha1($user.$this->token.$userhash);
        return $this->token;
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
      $user = $this->user;
      error_log("User '$user' logged out.");
    }
    $this->user     = Null;
    $this->token    = Null;
    $this->authhash = Null;
    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $nano = \Nano3\get_instance();
      $nano->sess->kill($restart_session);
    }
  }

}

// End of class.
