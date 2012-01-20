<?php

/* An extension of EasyController that has a supports authorization.
   This requires a get_user_info() method to be implemented to return
   the data for the user, likely using a model.
   You'll also want to ensure your model_opts are set.
 */

load_core('easycontroller');
load_core('easysession');
load_core('auth');

abstract class AuthController extends EasyController
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

    if (!isset($userid) || !isset($login) || !isset($hash))
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $user = $this->get_user_info($userid);

    if (!$user)
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $uhash = $user['hash'];

    if (!check_authorization($userid, $token, $uhash, $ahash))
    { if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

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

    $auth = login_authorization($user, $pass, $uhash);

    if (!$auth)
    { //error_log("our auth was invalid: ".json_encode($auth));
      return False;
    }

    $token = $auth['token'];
    $ahash = $auth['ahash'];

    puts('auth.user',  $user);
    puts('auth.token', $token);
    puts('auth.hash',  $ahash);

    $lastpath = gets('lastpath', 'AUTH');

    if (!isset($lastpath) || $lastpath == '' || $lastpath == '/login')
      $lastpath = '/';

    $this->redirect($lastpath);
  }

  protected function process_logout ()
  {
    error_log("Logging out");
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
