<?php

// This class represents a controller.

namespace ExampleApp;

abstract class Controller extends \Nano3\Base\Controller 
{
  protected $save_uri  = True;  // Set to False for login/logout pages.
  protected $need_user = False; // Set to True for user-only pages.
  protected $user;              // This will be set on need_user pages.

  // Add any extra rules you need for your controllers.

  public function __construct ($opts=array()) 
  {
    $nano = \Nano3\get_instance();
    $this->model_opts['users'] = $nano->conf->db;
    if (!isset($this->layout))
    {
      $this->layout = LAYOUT_DEFAULT;
    }
    if ($this->save_uri)
    {
      $nano->sess->lasturi = $this->request_uri();
    }
    if ($this->need_user)
    {
      $auth = \Nano3\Utils\SimpleAuth::getInstance();
      $userid = $auth->is_user();
      if ($userid)
      {
        $users = $this->model('users');
        $user  = $users->getUser($userid);
        if (!$user) 
        { 
          $this->redirect(PAGE_LOGIN); 
        }
        $this->user = $user;
        $this->data['user'] = $user;
      }
      else
      {
        $this->redirect(PAGE_LOGIN);
      }
    }
  }
}

