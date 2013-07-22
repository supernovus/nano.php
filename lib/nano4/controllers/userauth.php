<?php

namespace Nano4\Controllers;

/**
 * A Controller Trait for User authentication.
 */

trait UserAuth
{
  protected $save_uri  = True;  // Set to False for login/logout pages.
  protected $need_user = True;  // Set to False for non-user pages.
  protected $user;              // This will be set on need_user pages.

  protected function __construct_userauth_controller ($opts=[])
  {
    $nano = \Nano4\get_instance();
    if ($this->save_uri)
    {
      $nano->sess->lasturi = $this->request_uri();
    }
    if ($this->need_user)
    {
      $login = $this->get_page('login');
      $auth = \Nano4\Utils\SimpleAuth::getInstance();
      $userid = $auth->is_user();
      if ($userid)
      {
        $users = $this->model('users');
        $user  = $users->getUser($userid);
        if (!$user) 
        { 
          $this->redirect($login); 
        }
        if (method_exists($this, 'validate_user'))
        {
          $this->validate_user($user);
        }
        $this->user = $user;
        $this->data['user'] = $user;
        if (isset($user->lang) && $user->lang)
        {
          if (property_exists($this, 'lang'))
          {
            $this->lang = $user->lang;
          }
        }
      }
      else
      {
        $this->redirect($login);
      }
    }
  }

  // Get a user.
  public function get_user ()
  {
    if (isset($this->user))
    {
      return $this->user;
    }
  }


}
