<?php

namespace Nano4\Controllers;

/**
 * A Controller Trait for User authentication.
 */

trait UserAuth
{
  protected $user;              // This will be set on need_user pages.

  protected function __construct_userauth_controller ($opts=[])
  {
    if (property_exists($this, 'save_uri'))
      $save_uri = $this->save_uri;
    else
      $save_uri = True;

    if (property_exists($this, 'need_user'))
      $need_user = $this->need_user;
    else
      $need_user = True;

    $nano = \Nano4\get_instance();
    if ($save_uri)
    {
      $nano->sess->lasturi = $this->request_uri();
    }
    if ($need_user)
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
