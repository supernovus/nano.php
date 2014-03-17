<?php

namespace Nano4\Utils;

/**
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
 * The default storage model is to store the SimpleAuth object in the
 * session. We use the Nano4 Session helper to achieve this.
 * You can disable the autostoring, and store it yourself, or you can
 * create a subclass and override the store(), load() and update() methods.
 *
 */

class SimpleAuth
{
  public $log = False;          // Enable logging?

  protected $hashType = 'sha256'; // Default hash algorithm.

  // Internal fields.
  protected $userid;
  protected $authhash;

  // A failsafe mechanism for where you can't control the
  // location of the session_start() call in relations to
  // the SimpleAuth object being created.
  public static function preLoad () { return True; }

  // Build a new object.
  public function __construct ($opts=array())
  {
    if (isset($opts['log']))
    { 
      $this->log = $opts['log'];
    }

    if (isset($opts['hash']))
    {
      $this->hashType = $opts['hash'];
    }

    // To disable auto-store, pass 'store' => False
    // to the constructor/getInstance() options.
    if (!isset($opts['store']) || $opts['store'])
    {
      $this->store();
    }

  }

  // Method to store the authentication details to the session.
  // You can override this if the default implementation doesn't work
  // for your needs.
  public function store ()
  {
    $nano = \Nano4\get_instance();
    $nano->sess->SimpleAuth = $this;
  }

  // Static function to get an existing instance or create a new one.
  // Override this if you have changed the store() method.
  public static function getInstance ($opts=array())
  {
    $nano = \Nano4\get_instance();
    if (isset($nano->sess->SimpleAuth) && is_object($nano->sess->SimpleAuth))
    { 
      return $nano->sess->SimpleAuth;
    }
    return new SimpleAuth($opts);
  }

  // A method to update details. In this implementation we don't
  // use it, but if you change how session details are stored you'll
  // want to make your own version.
  protected function update () {}

  // Generate a password hash from a username and password.
  // If you override this, it will break compatibility with the
  // default implementation. The default version is designed to
  // be able to be called as a object or class method.
  public function generate_hash ($token, $pass)
  {
    return hash($this->hashType, trim($token.$pass));
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
        $checkhash = hash($this->hashType, $authtoken.$userhash);
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
    { $this->userid = $userid;
      if ($this->log) error_log("User '$userid' logged in.");
      if ($paranoid)
      {
        $authtoken = hash($this->hashType, time());
        $this->authhash = hash($this->hashType, $authtoken.$userhash);
        return $authtoken;
      }
      $this->update();
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
    $this->update();

    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $nano = \Nano4\get_instance();
      $nano->sess->kill($restart_session);
    }
  }

}

// End of class.
