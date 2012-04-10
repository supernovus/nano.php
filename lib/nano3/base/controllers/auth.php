<?php

/* 
 * Auth Controller: Handles login and logout pages.
 */

namespace Nano3\Base\Controllers;

abstract class Auth extends Advanced
{ 
  // Set up our basic settings.
  protected $need_user = False;
  protected $save_uri  = False;

  // Override these as you see fit.
  protected $view_login  = 'login';
  protected $view_forgot = 'forgot_password';
  protected $view_reset  = 'reset_password';

  protected function invalid ($message)
  {
    error_log($message);
    return $this->show_error('invalid');
  }

  public function handle_login ($opts, $path=Null)
  { // Let's log into the system.
    $this->screen = $this->view_login;
    if (method_exists($this, 'pre_login'))
    {
      $this->pre_login($opts);
    }
    if (isset($opts['user']) && $opts['pass'])
    {
      $user  = $opts['user'];
      $pass  = $opts['pass'];
      $users = $this->model('users');
      $uinfo = $users->getUser($user);
      if (!$uinfo)
      {
        return $this->invalid("Attempted login by unknown user '$user'.");
      }

      $auth = \Nano3\Utils\SimpleAuth::getInstance();

      $userid    = $uinfo->id;
      $userhash  = $uinfo->hash;
      $usertoken = $uinfo->token;

      $regenerate = False;
      if (!isset($usertoken) || $usertoken == '')
      { // If we have no token, we're using the old hashing algorithm.
        // The e-mail field was used in the hash. We'll verify the password,
        // then regenerate our hash using the new algorithm.
        $regengerate = True; 
        $usertoken = $uinfo->email;
      }

      if ($auth->login($userid, $pass, $userhash, $usertoken))
      {
        if ($regenerate)
        {
          // Change password will regenerate the token and hash.
          $uinfo->changePassword($pass);
        }
        if (method_exists($this, 'post_login'))
        {
          $this->post_login($opts);
        }
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
        return $this->invalid("Invalid login attempt for '$user'.");
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
  public function handle_forgot ($opts, $path)
  { $this->screen = $this->view_forgot; // The forgot password screen.
    $this->data['title'] = $this->text['forgotname'];
    if (isset($path) && is_array($path) && count($path)>1 && $path[1])
    { // We have a validation code.
      $nano = \Nano3\get_instance();
      $validCode = $path[1];
      $validInfo = $nano->url->decodeArray($validCode);
      if (!is_array($validInfo))
      {
        return $this->invalid("Invalid forgot password code: $validCode");
      }
      $uid  = $validInfo['uid'];
      $code = $validInfo['code'];
      $user = $this->model('users')->getUser($uid);
      if (!$user)
      {
        return $this->invalid("Invalid user id in forgot password: $uid");
      }
      if ($user->reset != $code)
      {
        return $this->invalid("Invalid reset code for '$uid': $code");
      }
      // Okay, if we made it this far, we're ready to reset the password.
      $this->screen = $this->view_reset;
      $this->data['title'] = $this->text['resetname'];
      if 
      (
        isset($opts['newpass']) && trim($opts['newpass']) != ''
        &&
        isset($opts['confpass']) && trim($opts['confpass']) != ''
      )
      { // We've submitted the form.
        if ($opts['newpass'] != $opts['confpass'])
        {
          return $this->show_error('nomatch');
        }
        // Okay, we made it this far, as the passwords match, let's reset.
        $user->resetReset(False);  // The old code no longer works.
        $user->changePassword($opts['newpass']);
        // Finally, let's login for the user.
        return $this->handle_login(array('user'=>$uid,'pass'=>$opts['newpass']));
      }
    }
    elseif (isset($opts['email']))
    { 
      $email = $opts['email'];
      $user = $this->model('users')->getUser($email);
      if (!$user)
      {
        return $this->invalid("Invalid e-mail passed to forget password: $email");
      }
      $nano = \Nano3\get_instance();
      $code = $user->resetReset();
      $uid  = $user->id;
      $validInfo = array('uid'=>$uid, 'code'=>$code);
      $validCode = $nano->url->encodeArray($validInfo);
      $mail_rules = array
      (
        'username' => True,
        'siteurl'  => True,
        'code'     => True,
      );
      $mail_opts = $nano->conf->mail;
      $mail_opts['views']    = 'messages';
      $mail_opts['subject']  = $this->text['subject'];
      $mail_opts['to']       = $user->email;
      $mail_opts['template'] = 'forgot_password';
      $mailer = new \Nano3\Utils\Mailer($mail_rules, $mail_opts);
      $mail_data = array
      (
        'username' => $user->name,
        'siteurl'  => $this->site_url(),
        'code'     => $validCode,
      );
      $mail->send($mail_data);
    }
    return $this->display();
  }
}

