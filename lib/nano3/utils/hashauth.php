<?php

/* HashAuth: An object to handle authentication via simple user-hashes.

   It does not specify the model of the users, and must be used in
   conjunction with a user model.

   It supports two forms of security:
     Default:  PHP sessions are trusted, and 'auth.user' is stored.
     Paranoid: In addition to the username, a token and security hash
               is stored, and compared to a calculated user hash.
               This is slower, but provides an additional layer of security.
 */

namespace Nano3\Utils;

class HashAuth
{
  public    $log      = false;   // Enable logging?
  protected $paranoid = false;   // Are we using paranoid mode?
  protected $userhash = array(); // Save the user hash for paranoid mode.

  public function __construct ($opts=array())
  {
    if (isset($opts['paranoid']))
      $this->paranoid = $opts['paranoid'];
    if (isset($opts['log']))
      $this->log = $opts['log'];
  }

  // Generate a password hash from a username and password.
  public function generate_hash ($user, $pass)
  {
    return sha1(trim($user.$pass));
  }

  // Returns if a user is logged in or not.
  // If a user is logged in, it returns their user id.
  // Otherwise, it returns false.
  public function is_user ()
  {
    // Basic auth, see if we have a 'auth.user' stored in our session.
    $user = gets('auth.user');
    if (!isset($user)) 
      return false;

    // Paranoid mode.
    if ($this->paranoid)
    {
      $token = gets('auth.token');
      $ahash = gets('auth.hash');
      if (!isset($token) || !isset($ahash))
        return false;

      $uhash = $this->userhash[$user];
      $chash = sha1($user.$token.$uhash);
      if (strcmp($ahash, $chash) != 0)
        return false;
    }

    return $user;
  }

  // Process a login request.
  public function login ($user, $pass, $uhash)
  {
    $chash = $this->generate_hash($user, $pass);
    if (strcmp($uhash, $chash) == 0)
    {
      puts('auth.user', $user);
      if ($this->paranoid)
      {
        $this->userhash[$user] = $uhash; 
        $token = time();
        $ahash = sha1($user.$token.$uhash);
        puts('auth.token', $token);
        puts('auth.hash',  $ahash);
      }
      if ($this->log) error_log("User '$user' logged in.");
      return true;
    }
    return false;
  }

  // Process a logout request.
  public function logout ($destroy_session=False, $restart_session=False)
  {
    if ($this->log)
    {
      $user = gets('auth.user');
      error_log("User '$user' logged out.");
    }
    if ($destroy_session)
    { // Destroy the entire session. A good way to log out.
      $_SESSION = array();
      if (ini_get("session.use_cookies"))
      { $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
          $params["path"],   $params["domain"],
          $params["secure"], $params["httponly"]
        );
      }
      session_destroy();
      if ($restart_session)
        session_start();
    }
    else
    { // Remove the 'auth.user' element from the session.
      dels('auth.user');
      if ($this->paranoid)
      { // And the token and hash if we're using paranoid mode.
        dels('auth.token');
        dels('auth.hash');
      }
    }
  }

}

// End of class.
