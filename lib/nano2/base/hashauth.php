<?php

/* HashAuth: An object to handle authentication via simple user-hashes.

   It does not specify the model of the users, and must be used in
   conjunction with a user model.

 */

$nano = get_nano_instance();
$nano->loadUtil('session');

class HashAuth
{
  public    $log      = false;   // Enable logging?

  public function __construct ($opts=array())
  {
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

    return $user;
  }

  // Process a login request.
  public function login ($user, $pass, $uhash)
  {
    $chash = $this->generate_hash($user, $pass);
    if (strcmp($uhash, $chash) == 0)
    {
      puts('auth.user', $user);
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
    }
  }

}

// End of class.
