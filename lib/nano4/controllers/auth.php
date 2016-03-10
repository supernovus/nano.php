<?php

/* 
 * Auth Controller Trait. Add to your own authentication controllers.
 * 
 *  It requires the Translation, Messages, and Mailer traits.
 *
 * Expects pages with names of 'default' and 'login', either using the
 * old $nano["page.$name"] or newer Router-based dispatching (preferred.)
 *
 * The Users model must have a getUser($identifier, $column=null) method that
 * takes a user id, e-mail address, or other identifiers, with an optional
 * column name if you don't want to use type detection. If no column is
 * specified, and the identifier is numeric, it should be assumed to be the
 * user id.
 */

namespace Nano4\Controllers;

trait Auth
{ 
  protected function __construct_auth_controller ($opts=[])
  {
    $this->set_prop('need_user',     False); // Auth screens require no user.
    $this->set_prop('save_uri',      False); // We don't want to save the URI.
    $this->set_prop('validate_user', False); // No user validation either.

    if (is_callable([$this, 'setup_auth']))
    {
      $this->setup_auth($opts);
    }
  }

  protected function invalid ($message, $context=null, $log=null, $user=null)
  {
    error_log($message);
    if (isset($log))
    {
      $logopts = ['success'=>false, 'message'=>$message, 'context'=>$context];
      if (isset($user))
        $logopts['user'] = $user;
      $log->log($logopts);
    }
    return $this->show_error('invalid');
  }

  public function handle_login ($opts, $path=Null)
  { // Let's log into the system.
    $this->screen = $this->get_prop('view_login', 'login');

    $ukey = $this->get_prop('username_field',  'user');
    $pkey = $this->get_prop('password_field',  'pass');
    $ucol = $this->get_prop('username_column', 'email');
    $tval = $this->get_prop('idle_timeout',     0);

    if (method_exists($this, 'pre_login'))
    {
      $this->pre_login($opts);
    }
    if (isset($opts[$ukey]) && $opts[$pkey])
    {
      $user  = $opts[$ukey];
      $pass  = $opts[$pkey];
      $model = $this->get_prop('users_model', 'users');
      $users = $this->model($model);

      $logm  = $this->get_prop('userlog_model');
      if (isset($logm))
        $userlog = $this->model($logm);
      else
        $userlog = null;

      $uinfo = $users->getUser($user, $ucol);
      if (!$uinfo)
      {
        return $this->invalid("Attempted login by unknown user '$user'.", $opts, $userlog);
      }

      // Before we continue, let's see if we have a user check.
      if (method_exists($this, 'verify_login'))
      {
        if (!$this->verify_login($uinfo))
        {
          return $this->invalid("Unauthorized user '$user' tried to log in.", $opts, $userlog, $uinfo);
        }
      }

      // Get our authentication library.
      $auth = $users->get_auth(true,true);

      $userid    = $uinfo->id;
      $userhash  = $uinfo->hash;
      $usertoken = $uinfo->token;

      if (method_exists([$this, 'get_user_timeout']))
      {
        $tval = $this->get_user_timeout($uinfo, $tval);
      }

      $regenerate = False;
      if (!isset($usertoken) || $usertoken == '')
      { // If we have no token, we're using the old hashing algorithm.
        // The e-mail field was used in the hash. We'll verify the password,
        // then regenerate our hash using the new algorithm.
        $regengerate = True; 
        $usertoken = $uinfo->email;
      }

      if ($auth->login($userid, $pass, $userhash, $usertoken, $tval))
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

        if (isset($userlog))
          $userlog->log(['success'=>true, 'context'=>$opts, 'user'=>$uinfo]);

        $nano = \Nano4\get_instance();
        if (isset($nano->sess->lasturi))
        {
          $lastpath = $nano->sess->lasturi;
        }
        else
        {
          $lastpath = null;
        }
        $default_page = $this->get_prop('default_page', 'default');
        if (!$lastpath || $lastpath = $this->request_uri())
        { // Go to the default page.
          $this->go($default_page);
        }
        $this->redirect($lastpath);
      }
      else
      {
        return $this->invalid("Invalid login attempt for '$user'." , $opts, $userlog, $uinfo);
      }
    }
    return $this->display();
  }

  public function handle_logout ($opts, $path=Null)
  {
    $model = $this->get_prop('users_model', 'users');
    $users = $this->model($model);
    $auth  = $users->get_auth(true); 
    if (method_exists($this, 'pre_logout'))
    {
      $this->pre_logout($auth, $opts);
    }

    $auth->logout(True);

    if (method_exists($this, 'post_logout'))
    {
      $this->post_logout($auth, $opts);
    }

    $login_page = $this->get_prop('login_page', 'login');
    $this->go($login_page);
  }

  // Get the validation code.
  protected function get_validation_code ($opts, $path)
  {
    $validCode = Null;
    $vkey = $this->get_prop('validation_field', 'validationCode');
    if (isset($vkey, $opts[$vkey]))
    { // Router dispatch uses named parameters.
      $validCode = $opts[$vkey];
    }
    elseif (isset($path) && is_array($path) && count($path)>1 && $path[1])
    { // Older dispatch uses positional parameters.
      $validCode = $path[1];
    }
    return $validCode;
  }

  // Forgot password, handler function.
  public function handle_forgot ($opts, $path=Null)
  {
    $validCode = $this->get_validation_code($opts, $path);
    if (isset($validCode))
    {
      $this->screen = $this->get_prop('view_reset',  'reset_password');
      $this->data['title'] = $this->text['title.reset'];
      return $this->check_reset_code($validCode, $opts);
    }
    else
    {
      $this->screen = $this->get_prop('view_forgot', 'forgot_password');
      $this->data['title'] = $this->text['title.forgot'];
      $ekey = $this->get_prop('email_field',  'email');
      $ecol = $this->get_prop('email_column', 'email');
      if (isset($opts[$ekey]))
      {
        $email = $opts[$ekey];
        $model = $this->get_prop('users_model', 'users');
        $user  = $this->model($model)->getUser($email, $ecol);
        if (!$user)
        { // TODO: enable privacy mode, where no warning is issued if the
          // user puts in an invalid email address.
          return $this->invalid("Invalid e-mail in handle_forget: $email");
        }
        $template = $this->get_prop('email_forgot',   'forgot_password');
        $subject  = $this->get_prop('subject_forgot', 'subject.forgot');
        $subject  = $this->text[$subject];
        $this->mail_reset_pw($user, $opts, $template, $subject);
      }
    }
    return $this->display();
  }

  // Activate account, handler function.
  // This shares the same backend as forgot password, but uses different
  // messages.
  public function handle_activate ($opts, $path=Null)
  {
    $validCode = $this->get_validation_code($opts, $path);
    if (isset($validCode))
    {
      $this->screen = $this->get_prop('view_activate',  'activate_account');
      $this->data['title'] = $this->text['title.activate'];
      return $this->check_reset_code($validCode, $opts);
    }
    else
    {
      return $this->go_error('no_code', 'error');
    }
    return $this->display();
  }

  // Backend method to check the supplied reset code.
  // And in the case that a new password has been supplied, reset it.
  protected function check_reset_code ($validCode, $opts)
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
        return $this->invalid("Failed pre_reset test.");
      }
    }

    $uid   = $validInfo['uid'];
    $code  = $validInfo['code'];
    $model = $this->get_prop('users_model', 'users');
    $user  = $this->model($model)->getUser($uid);
    if (!$user)
    {
      return $this->invalid("Invalid user id in forgot password: $uid");
    }
    if ($user->reset != $code)
    {
      return $this->invalid("Invalid reset code for '$uid': $code");
    }

    $p1key = $this->get_prop('newpass1_field', 'newpass');
    $p2key = $this->get_prop('newpass2_field', 'confpass');

    if 
    (
      isset($opts[$p1key]) && trim($opts[$p1key]) != ''
      &&
      isset($opts[$p2key]) && trim($opts[$p2key]) != ''
    )
    { // We've submitted the form.
      if ($opts[$p1key] != $opts[$p2key])
      {
        return $this->show_error('nomatch');
      }
      // Okay, we made it this far, as the passwords match, let's reset.
      $user->resetReset(False);  // The old code no longer works.
      $user->changePassword($opts[$p1key]);

      if (method_exists($this, 'post_reset'))
      {
        $this->post_reset($user, $opts);
      }

      $ukey = $this->get_prop('username_field', 'user');
      $pkey = $this->get_prop('password_field', 'pass');

      // Finally, let's login for the user.
      return $this->handle_login([$ukey=>$uid, $pkey=>$opts['newpass']]);
    }
    return $this->display();
  }

  // A method to be called from an administrative frontend.
  // This is one of the few cases where one controller calls another
  // directly as a helper object.
  public function send_activation_email ($user, $opts=[])
  {
    if 
    ( // Ensure it's a valid User object.
      isset($user) 
      && is_object($user)
      && isset($user->id) 
      && isset($user->hash) 
      && isset($user->token)
      && isset($user->email)
    )
    {
      $template = $this->get_prop('email_activate',   'activate_account');
      $subject  = $this->get_prop('subject_activate', 'subject.activate');
      $subject  = $this->text[$subject];
      return $this->mail_reset_pw($user, $opts, $template, $subject);
    }
    else
    {
      throw new \Exception("send_activation_email: invalid user");
    }
  }

  // Backend method to start the reset process and send an e-mail to the
  // user with the appropriate message.
  protected function mail_reset_pw ($user, $opts, $template, $subject)
  {
    // Pre-email check, if it returns false, we fail.
    // You can populate $opts with extended data if required.
    if (method_exists($this, 'pre_email'))
    {
      if (!$this->pre_email($user, $opts))
      { // Cannot continue.
        return False;
      }
    }

    // Get our required information.
    $nano = \Nano4\get_instance();
    $code = $user->resetReset();
    $uid  = $user->id;

    // Set up a validation code to send to the user.
    $validInfo = array('uid'=>$uid, 'code'=>$code);
    $validCode = $nano->url->encodeArray($validInfo);

    // E-mail rules for the Nano mailer.
    $mail_rules = array
    (
      'username' => True,
      'siteurl'  => True,
      'code'     => True,
    );

    // Our mailer options.
    $mail_opts             = $nano->conf->mail;
    $mail_opts['views']    = $this->get_prop('email_views', 'mail_messages');
    $mail_opts['subject']  = $subject;
    $mail_opts['to']       = $user->email;
    $mail_opts['template'] = $template;

    // Populate $mail_rules and $mail_opts with further data here.
    if (method_exists($this, 'prep_email_options'))
    {
      $this->prep_email_options($mail_opts, $mail_rules, $opts);
    }

    // Build our mailer object.
    $mailer = new \Nano4\Utils\Mailer($mail_rules, $mail_opts);

    // The message data for the template.
    $mail_data = array
    (
      'username' => $user->name ? $user->name : $user->email,
      'siteurl'  => $this->url(),
      'code'     => $validCode,
    );

    // Populate $mail_data, and make any changes to $mailer here.
    if (method_exists($this, 'prep_email_data'))
    {
      $this->prep_email_data($mailer, $mail_data, $opts);
    }

    // Send the message.
    $sent = $mailer->send($mail_data);

    // One last check after sending the message.
    if (method_exists($this, 'post_email'))
    {
      $this->post_email($mailer, $sent, $opts);
    }

    // Return the response from $mailer->send();
    return $sent;
  } // end function mail_reset_pw();

} // end trait Auth

