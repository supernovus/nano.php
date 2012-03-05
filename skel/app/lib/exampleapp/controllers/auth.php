<?php

/* Auth Controller: Handles login and logout pages. */

namespace ExampleApp\Controllers;

class Auth extends \ExampleApp\Controller
{ // Set up our basic settings.
  protected $need_user = False;
  protected $save_uri  = False;
  public function handle_login ($opts, $path)
  { // Let's log into the system.
    $this->screen = 'login';
    $this->data['title'] = "Login";
    if (isset($opts['user']) && $opts['pass'])
    {
      $user  = $opts['user'];
      $pass  = $opts['pass'];
      $users = $this->model('users');
      $uinfo = $users->getUser($user);
      if (!$uinfo)
      {
        error_log("Attempted login by unknown user '$user'.");
        return $this->display(array('err'=>'invalid'));
      }

      $auth = \Nano3\Utils\SimpleAuth::getInstance();

      $userid    = $uinfo->id;
      $userhash  = $uinfo->hash;
      $usertoken = $uinfo->token;

      if ($auth->login($userid, $pass, $userhash, $usertoken))
      {
        $nano = \Nano3\get_instance();
        $lastpath = $nano->sess->lasturi;
        if (!$lastpath || $lastpath = $this->request_uri())
        {
          $lastpath = PAGE_DEFAULT;
        }
        $this->redirect($lastpath);
      }
      else
      {
        error_log("Invalid login attempt for '$user'.");
        return $this->display(array('err'=>'invalid'));
      }
    }
    return $this->display();
  }
  public function handle_logout ($opts, $path)
  {
    $auth = \Nano3\Utils\SimpleAuth::getInstance();
    $auth->logout(True);
    $this->redirect(PAGE_LOGIN);
  }
}

