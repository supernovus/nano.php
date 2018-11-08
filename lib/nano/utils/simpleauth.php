<?php

namespace Nano\Utils;

/**
 * SimpleAuth: An object to handle authentication via simple user-hashes.
 *
 * An extremely basic user authentication system using a simple
 * hash-based approach. It has no specifications as to the user model,
 * other than it needs to store the hash provided by the generate_hash
 * method.
 *
 * The default storage model is to store the SimpleAuth object in the
 * session. We use the Nano Session helper to achieve this.
 * You can disable the autostoring, and store it yourself, or you can
 * create a subclass and override the store(), load() and update() methods.
 *
 */

class SimpleAuth
{
  public $log = False;            // Enable logging?

  // If this is set to null, we will default to using hash() instead.
  protected $passwordHash = PASSWORD_DEFAULT;
  protected $pwHashOpts   = [];   // Options for password_hash().
  protected $pwHashHeader = '$';  // Hashes from password_hash() start with.

  protected $hashType = 'sha256'; // Default hash algorithm if using hash().

  protected $userid;              // The currently logged in user.

  protected $timeout = 0;         // Num if idle seconds until timeout.
  protected $accessed;            // Last accessed time.

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

    if (isset($opts['passwordHash']))
    {
      $this->passwordHash = $opts['passwordHash'];
    }

    if (isset($opts['passwordHashOpts']))
    {
      $this->pwHashOpts = $opts['passwordHashOpts'];
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
    $nano = \Nano\get_instance();
    $nano->sess->SimpleAuth = $this;
  }

  // Static function to get an existing instance or create a new one.
  // Override this if you have changed the store() method.
  public static function getInstance ($opts=array())
  {
#    error_log('SimpleAuth::getInstance('.json_encode($opts).')');
    $nano = \Nano\get_instance();
    $nano->sess; // Make sure the plugin is initialized.
    if (isset($nano->sess->SimpleAuth) && is_object($nano->sess->SimpleAuth))
    { 
#      error_log(" -- loading session");
      return $nano->sess->SimpleAuth;
    }
#    error_log(" -- creating session");
    return new SimpleAuth($opts);
  }

  // A method to update details. In this implementation we don't
  // use it, but if you change how session details are stored you'll
  // want to make your own version.
  protected function update () {}

  // Generate a password hash from a username and password.
  // If you override this, it will break compatibility with the
  // default implementation.
  public function generate_hash ($token, $pass, $forceHash=false)
  {
    if ($forceHash || !isset($this->passwordHash))
    {
      return hash($this->hashType, trim($token.$pass));
    }
    else
    {
      return password_hash(trim($token.$pass), $this->passwordHash);
    }
  }

  // Returns if a user is logged in or not.
  // If a user is logged in, it returns their user id.
  // Otherwise, it returns false.
  public function is_user ()
  { 
    if (isset($this->userid))
    {
      if ($this->timeout > 0)
      {
        $curtime = time();
        $oldtime = $this->accessed;
        if (($curtime - $oldtime) > $this->timeout)
        {
          return false;
        }
        $this->accessed = $curtime;
      }
      return $this->userid;
    }
    return False;
  }

  // Process a login request.
  public function login 
    ($userid, $pass, $userhash, $usertoken=Null, $timeout=Null)
  {
    // If we don't specify a user token, assume the same as userid.
    if (is_null($usertoken))
      $usertoken = $userid;

    if (isset($timeout) && $timeout > 0)
    {
      $this->timeout = $timeout;
      $this->accessed = time();
    }

    if ($this->check_credentials($usertoken, $pass, $userhash))
    { 
      $this->setUser($userid);
      if ($this->log) error_log("User '$userid' logged in.");
      return true;
    }
    return false;
  }

  // Set the user id that's currently logged in.
  public function setUser ($userid)
  {
    $this->userid = $userid;
    $this->update();
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
    $this->update();

    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $nano = \Nano\get_instance();
      $nano->sess->kill($restart_session);
    }
  }

  public function check_credentials ($usertoken, $pass, $userhash)
  {
    if ($this->is_password_hash($userhash))
    { // The hash is in the new format.
      return password_verify(trim($usertoken.$pass), $userhash);
    }
    else
    { // Use the old hash algorithm.
      $checkhash = $this->generate_hash($usertoken, $pass, true);
      return (strcmp($userhash, $checkhash) == 0);
    }
  }

  public function is_password_hash ($userhash)
  {
    $pwheader = $this->pwHashHeader;
    return (substr($userhash, 0, strlen($pwheader)) == $pwheader);
  }

  public function hash_is_current ($userhash)
  {
    $ispw = $this->is_password_hash($userhash);
    if (isset($this->passwordHash))
    { // We want a password_hash() generated hash.
      return $ispw;
    }
    else
    { // We want a hash() generated hash.
      return (!$ispw);
    }
  }

}

// End of class.
