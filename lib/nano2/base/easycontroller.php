<?php

/* EasyController: An extension of CoreController using the HashAuth
   authentication system to handle user authentication.
   It's a good starting point, but means you need to use the HashAuth
   style authentication hashes in your user model.
 */

$nano = get_nano_instance();
$nano->loadBase('corecontroller');
$nano->loadBase('hashauth');

// Use this to return a password hash.
function easy_password_hash ($user, $pass)
{
  return HashAuth::generate_hash($user,$pass);
}

abstract class EasyController extends CoreController
{

  // get_user_info() must return an assoc array that contains at least a 'hash'
  // member, representing the SHA1 of the userid and password concatted.
  abstract protected function get_user_info ($userid);

  protected $login_url = '/login'; // A page to redirect to for login purposes.
  protected $auth_opts = array();  // Options for the HashAuth object.
  protected $auth_object;          // The actual HashAuth object.

  // Get our auth object.
  public function get_auth ()
  {
    if (!isset($this->auth_object))
      $this->auth_object = new HashAuth($this->auth_opts);
    return $this->auth_object;
  }

  public function get_user ($redirect=false, $adddata=true)
  {
    $lastpath = $this->request_uri();
    puts('auth.lastpath', $lastpath);

    $auth = $this->get_auth();
    $uid  = $auth->is_user();

    if ($uid === False)
    {
      $user = null; // Sorry, user not found.
    }
    else
    {
      $user = $this->get_user_info($uid);
    }

    if (!isset($user))
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return False;
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

    $auth = $this->get_auth();
    if (!$auth->login($user, $pass, $uhash))
      return False;

    $lastpath = gets('auth.lastpath');

    if (!isset($lastpath) || $lastpath == '' || $lastpath == '/login')
      $lastpath = '/';

    $this->redirect($lastpath);
  }

  protected function process_logout ($wipeout=False)
  {
    $auth = $this->get_auth();
    $auth->logout($wipeout);
    $this->redirect();
  }

}

// End of base class.

