<?php

namespace Nano\Models\Common;

trait User
{
  abstract public function save ($opts=[]);
  abstract public function get_id ();

  /**
   * Change our password.
   *
   * @param String $newpass      The new password
   * @param Bool   $autosave     Save automatically (default True)
   */
  public function changePassword ($newpass, $autosave=True)
  { // We auto-generate a unique token every time we change the password.
    $hash = $this->parent->hash_type();
    $auth = $this->parent->get_auth();
    $tfield = $this->parent->token_field();
    $hfield = $this->parent->hash_field();
    $this->$tfield = hash($hash, time());
    $this->$hfield = $auth->generate_hash($this->token, $newpass);
    if ($autosave) $this->save();
  }

  /** 
   * Reset our reset code to a unique value.
   *
   * @param Bool $autosave    Save automatically (default True)
   *
   * @return String           The new reset code is returned.
   */
  public function resetReset ($autosave=True)
  {
    $rfield = $this->parent->reset_field();
    $reset = $this->$rfield = uniqid(base64_encode($this->id), True);
    if ($autosave) $this->save();
    return $reset;
  }

  /**
   * Change our login field, ensuring that it is unique.
   *
   * @param String $newlogin     The new login value.
   * @param Bool   $autosave     Save automatically (default True)
   *
   * @return Bool                False means e-mail address already in use.
   *                             True means we updated successfully.
   */
  public function changeLogin ($newlogin, $autosave=True)
  {
    $lfield = $this->parent->login_field();
    if ($this->parent->getUser($newlogin, $lfield))
    {
      return False; // Sorry, e-mail already in use.
    }
    $this->$lfield = $newlogin;
    if ($autosave) $this->save();
    return True;
  }

  // Backend method to start the reset process and send an e-mail to the
  // user with the appropriate message.
  protected function mail_reset_pw ($template, $subject, $opts=[])
  {
    $ctrl = $this->parent->parent;

    // Pre-email check, if it returns false, we fail.
    // You can populate $opts with extended data if required.
    if (method_exists($this, 'pre_email'))
    {
      if (!$this->pre_email($opts))
      { // Cannot continue.
        return False;
      }
    }
    elseif (is_callable([$ctrl, 'pre_email']))
    {
      if (!$ctrl->pre_email($this, $opts))
      {
        return false;
      }
    }

    // Get our required information.
    $nano = \Nano\get_instance();
    $code = $this->resetReset();
    $uid  = $this->get_id();

    // Set up a validation code to send to the user.
    $validInfo = array('uid'=>$uid, 'code'=>$code);
    $validCode = $nano->url->encodeArray($validInfo);

    // E-mail rules for the Nano mailer.
    $mail_rules = isset($opts['mail_rules']) ? $opts['mail_rules'] : [];
    $mail_rules['username'] = true;
    $mail_rules['siteurl']  = true;
    $mail_rules['code']     = true;

    // Our mailer options.
    $mail_opts             = $nano->conf->mail;
    $mail_opts['views']    = isset($opts['view_loader']) 
      ? $opts['view_loader'] : 'mail_messages';
    $mail_opts['subject']  = $subject;
    $mail_opts['to']       = $this->email;
    $mail_opts['template'] = $template;

    if (property_exists($this, 'email_class') 
        && isset($this->email_class) && !isset($mail_opts['handler']))
    {
      $mail_opts['handler'] = $this->email_class;
    }

    // Populate $mail_rules and $mail_opts with further data here.
    if (method_exists($this, 'prep_email_options'))
    {
      $this->prep_email_options($mail_opts, $mail_rules, $opts);
    }
    elseif (is_callable([$ctrl, 'prep_email_options']))
    {
      $ctrl->prep_email_options($mail_opts, $mail_rules, $opts);
    }

    // Build our mailer object.
    $mailer = new \Nano\Utils\Mailer($mail_rules, $mail_opts);

    // The message data for the template.
    $mail_data = isset($opts['mail_data']) ? $opts['mail_data'] : [];
    $mail_data['username'] = $this->name ? $this->name : $this->email;
    $mail_data['siteurl']  = $ctrl->url();
    $mail_data['code']     = $validCode;

    // Populate $mail_data, and make any changes to $mailer here.
    if (method_exists($this, 'prep_email_data'))
    {
      $this->prep_email_data($mailer, $mail_data, $opts);
    }
    elseif (is_callable([$ctrl, 'prep_email_data']))
    {
      $ctrl->prep_email_data($mailer, $mail_data, $opts);
    }

    // Send the message.
    $sent = $mailer->send($mail_data);

    // One last check after sending the message.
    if (method_exists($this, 'post_email'))
    {
      $this->post_email($mailer, $sent, $opts);
    }
    elseif (is_callable([$ctrl, 'post_email']))
    {
      $ctrl->post_email($mailer, $sent, $opts);
    }

    // Return the response from $mailer->send();
    return $sent;
  } // end function mail_reset_pw();

  public function send_activation_email ($opts=[])
  {
    if ($this->get_id())
    { // Make sure we have an id, and are thus a saved user.
      $template = isset($opts['template']) ? $opts['template'] :
        'activate_account';
      $subject = isset($opts['subject']) ? $opts['subject'] :
        'subject.activate';
      if (!isset($opts['translate_subject']) || !$opts['translate_subject'])
      {
        $ctrl = $this->parent->parent;
        $text = $ctrl->get_text();
        $subject = $text[$subject];
      }
      return $this->mail_reset_pw($template, $subject, $opts);
    }
  }

  public function send_forgot_email ($opts=[])
  {
    if ($this->get_id())
    { // Make sure we have an id, and are thus a saved user.
      $template = isset($opts['template']) ? $opts['template'] :
        'forgot_password';
      $subject = isset($opts['subject']) ? $opts['subject'] :
        'subject.forgot';
      if (!isset($opts['translate_subject']) || !$opts['translate_subject'])
      {
        $ctrl = $this->parent->parent;
        $text = $ctrl->get_text();
        $subject = $text[$subject];
      }
      return $this->mail_reset_pw($template, $subject, $opts);
    }
  }

}
