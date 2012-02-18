<?php

/* EasyController: An extension of CoreController with a simple
   authentication system built-in. It's a good starting point if you want
   something easy to get going with.
 */

$nano = get_nano_instance();
$nano->loadBase('corecontroller');
$nano->loadUtil('session');

// Use this to return a password hash.
function easy_password_hash ($user, $pass)
{
  return sha1(trim($user.$pass));
}

abstract class EasyController extends CoreController
{

  // get_user_info() must return an assoc array that contains at least a 'hash'
  // member, representing the SHA1 of the userid and password concatted.
  abstract protected function get_user_info ($userid);

  protected $login_url = '/login'; // A page to redirect to for login purposes.

  public function get_user ($redirect=false, $adddata=true)
  {
    $lastpath = get_path();
    puts('auth.lastpath', $lastpath);

    $userid = gets('auth.user');
    $token  = gets('auth.token');
    $ahash  = gets('auth.hash');

#    error_log("u: '$userid', t: '$token', h: '$ahash'");

    if (!isset($userid) || !isset($token) || !isset($ahash))
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $user = $this->get_user_info($userid);

#    error_log("checking user");

    if (!$user)
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $uhash = $user['hash'];

#    error_log("checking authorization");

    $chash = sha1($userid.$token.$uhash);
    if (strcmp($ahash, $chash) != 0)
    { if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

#    error_log("we succeeded.");

    if ($adddata)
      $this->data['user'] = $user;

    return $user;
  }

  // If successful this will redirect. If not, it will return false.
  protected function process_login ($user, $pass)
  {
    $uconf = $this->get_user_info($user);
    if (!$uconf)
    { //error_log("user config was invalid: ".json_encode($uconf));
      return False;
    }

    $uhash = $uconf['hash'];

    $chash = easy_password_hash($user, $pass);
    if (strcmp($uhash, $chash) == 0)
    {
      // We logged in successfully.
      $token = time();
      $ahash = sha1($user.$token.$uhash);
    }
    else
      return False;

    puts('auth.user',  $user);
    puts('auth.token', $token);
    puts('auth.hash',  $ahash);

    $lastpath = gets('lastpath', 'AUTH');

    if (!isset($lastpath) || $lastpath == '' || $lastpath == '/login')
      $lastpath = '/';

    error_log("User '$user' logged in.");

    $this->redirect($lastpath);
  }

  protected function process_logout ()
  {
    $user = gets('auth.user');
    error_log("User '$user' logged out.");
    $_SESSION = array();
    if (ini_get("session.use_cookies"))
    { $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params["path"],   $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    session_destroy();
    $this->redirect();
  }

}

// End of base class.

