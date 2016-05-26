<?php

namespace Nano4\Controllers;

/**
 * A Controller Trait for User authentication.
 *
 * This neesd the ModelConf trait.
 */

trait UserAuth
{
  protected $user;              // This will be set on need_user pages.

  protected function __construct_userauth_controller ($opts=[])
  {
    // Ensure that modelconf is loaded first.
    $this->needs('modelconf', $opts);

    // If the 'auth' trait is available, load it first.
    $this->wants('auth', $opts);

    // If the 'webapi' trait is available, load it first.
    $this->wants('webapi', $opts);

    // Some configurable settings, with appropriate defaults.
    $save_uri    = $this->get_prop('save_uri',    True);
    $need_user   = $this->get_prop('need_user',   True);
    
    $nano = \Nano4\get_instance();
    if ($save_uri)
    {
      $nano->sess->lasturi = $this->request_uri();
    }

    if ($need_user)
    {
      $this->need_user();
    }
  }

  /**
   * Call from methods where we need the user on a per method basis,
   * but not from a per-controller basis (make sure to disable the
   * 'need_user' property.)
   */
  protected function need_user ()
  {
    $login_page  = $this->get_prop('login_page',  'login');
    $user = $this->get_user(true);
    if (!$user) 
    { 
      $this->go($login_page); 
    }
    $this->user = $user;
    $this->data['user'] = $user;
    if (isset($user->lang) && $user->lang)
    {
      $this->set_prop('lang', $user->lang);
    }
  }

  /**
   * Get the currently authenticated user.
   */
  public function get_user ($checkauth=false)
  {
    if (isset($this->user))
    {
      return $this->user;
    }
    elseif ($checkauth)
    {
      $users_model = $this->get_prop('users_model', 'users');
      $users = $this->model($users_model);
      $auth = $users->get_auth(true);
      $userid = $auth->is_user();
      if ($userid)
      {
        return $users->getUser($userid);
      }
    }
  }

}
