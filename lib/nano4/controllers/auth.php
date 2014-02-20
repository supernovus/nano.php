<?php

/* 
 * Auth Controller Trait. Add to your own authentication controllers.
 * 
 *  It requires the Translation trait.
 *  It recommends the Constructor trait.
 *
 * You should define "page.default" and "page.login" options.
 */

namespace Nano4\Controllers;

trait Auth
{ 
  protected function __construct_auth_controller ($opts=[])
  {
    $this->need_user = False;
    $this->save_uri  = False;
  }

  protected function invalid ($message)
  {
    error_log($message);
    return $this->show_error('invalid');
  }

  public function handle_login ($opts, $path=Null)
  { // Let's log into the system.
    if (method_exists($this, 'prep_auth'))
    {
      $this->prep_auth($opts);
    }
    $this->screen = $this->get_prop('view_login', 'login');
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

      // Before we continue, let's see if we have a user check.
      if (method_exists($this, 'verify_login'))
      {
        if (!$this->verify_login($uinfo))
        {
          return $this->invalid("Unauthorized user '$user' tried to log in.");
        }
      }

      $auth = \Nano4\Utils\SimpleAuth::getInstance();

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
          $this->post_login($opts, $uinfo);
        }
        $nano = \Nano4\get_instance();
        $lastpath = $nano->sess->lasturi;
        $default_page = $this->get_prop('default_page', 'default');
        if (!$lastpath || $lastpath = $this->request_uri())
        { // Go to the default page.
          $this->go($default_page);
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

  public function handle_logout ($opts, $path=Null)
  {
    $auth = \Nano4\Utils\SimpleAuth::getInstance();
    $auth->logout(True);
    $login_page = $this->get_prop('login_page', 'login');
    $this->go($login_page);
  }

  public function handle_forgot ($opts, $path=Null)
  { // Welcome to Forgot password.
    if (method_exists($this, 'prep_auth'))
    {
      $this->prep_auth($opts);
    }
    $this->screen = $this->get_prop('view_forgot', 'forgot_password'); 
    $this->data['title'] = $this->text['title.forgot'];
    $validCode = Null;
    if (isset($opts['validationCode']))
    { // Router dispatch uses named parameters.
      $validCode = $opts['validationCode'];
    }
    elseif (isset($path) && is_array($path) && count($path)>1 && $path[1])
    { // Older dispatch uses positional parameters.
      $validCode = $path[1];
    }

    if (isset($validCode))
    {
      $nano = \Nano4\get_instance();
      $validInfo = $nano->url->decodeArray($validCode);
      if (!is_array($validInfo))
      {
        return $this->invalid("Invalid forgot password code: $validCode");
      }

      if (method_exists($this, 'pre_reset'))
      {
        if (!$this->pre_reset($validInfo, $opts))
        { // We cannot continue.
          return;
        }
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
      $this->screen = $this->get_prop('view_reset', 'reset_password');
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

        if (method_exists($this, 'post_reset'))
        {
          $this->post_reset($user, $opts);
        }

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

      if (method_exists($this, 'pre_email'))
      {
        if (!$this->pre_email($user, $opts))
        { // Cannot continue.
          return;
        }
      }

      $nano = \Nano4\get_instance();
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
      $mail_opts['views']    = 'mail_messages';
      $mail_opts['subject']  = $this->text['subject'];
      $mail_opts['to']       = $user->email;
      $mail_opts['template'] = 'forgot_password';
      $mailer = new \Nano4\Utils\Mailer($mail_rules, $mail_opts);
      $mail_data = array
      (
        'username' => $user->name,
        'siteurl'  => $this->site_url(),
        'code'     => $validCode,
      );
      if (method_exists($this, 'prep_email'))
      {
        $this->prep_email($email, $opts);
      }
      $mail->send($mail_data);
      if (method_exists($this, 'post_email'))
      {
        $this->post_email($mail, $opts);
      }
    }
    return $this->display();
  }
}

