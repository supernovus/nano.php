<?php

namespace Nano\Controllers;

/**
 * A Controller Trait for Authentication and Authorization.
 *
 * This needs the ModelConf trait.
 */

trait Auth
{
  protected $user; // This will be set on need_user pages.
  protected $auth_config; // This may be set if using auth_config option.
  protected $auth_prefix = 'auth'; // For overriding options.

  public $auth_errors = []; // A place to put error codes.

  protected function __init_auth_controller ($opts)
  {
    // If Auth\Users trait is available, load it first.
    $this->wants('authusers');

    // Some configurable settings, with appropriate defaults.
    $save_uri    = $this->get_prop('save_uri',        true);
    $need_user   = $this->get_prop('need_user',       false);
    $auth_config = $this->get_prop('use_auth_config', false);
    
    $nano = \Nano\get_instance();
    if ($auth_config)
    {
      if (!is_string($auth_config))
        $auth_config = 'auth';
      $auth_config = $nano->conf[$auth_config];
    }

    if (is_callable([$this, 'setup_auth']))
    {
      $this->setup_auth($opts, $auth_config);
    }

    $this->auth_config = $auth_config;

    if ($save_uri)
    {
      $nano->sess; // Make sure it's initialized.
      $nano->sess->lasturi = $this->request_uri();
#      error_log("saved URI: ".$nano->sess->lasturi);
    }

    if ($need_user)
    {
      $this->need_user();
    }
  }

  protected function get_auth ($context, $aopts=[])
  {
    if (!isset($this->auth_config)) return false; 

    $conf = $this->auth_config;

    $vmethod = isset($aopts['validationMethod'])
      ? $aopts['validationMethod']
      : 'validate_user';

    $skipUsers = isset($aopts['skipUsers']) ? $aopts['skipUsers'] : false;

    if (isset($conf['userAccess']) && $conf['userAccess'] && !$skipUsers)
    {
      $user = $this->get_user(true);
      if ($user)
      {
        if (isset($conf['validateUser']) && $conf['validateUser'] && is_callable([$this, $vmethod]))
        {
          return $this->$vmethod($user);
        }
        else
        { // Assume  validation is true.
          return true;
        }
      }
    }

    if (isset($conf['authPlugins']))
    {
      $prefix = $this->auth_prefix;
      foreach ($conf['authPlugins'] as $plugname => $plugconf)
      {
        if ($plugconf === false) continue; // skip disabled plugins.
        $classname = "\\Nano\\Controllers\\Auth\\$plugname";
        $plugin = new $classname(['parent'=>$this]);
        $options = $plugin->options($plugconf);
        $plugopts = ['context'=>$context];
        $overrides = [$prefix.'_'.strtolower($plugname), $prefix];
        foreach ($options as $option)
        {
          if ($option == 'context') continue; // sanity check.
          foreach ($overrides as $override)
          {
            $value = $this->get_prop($override.'_'.$option);
            if (isset($value))
            {
              $plugopts[$option] = $value;
              break; // We found an option, go onto next.
            }
          }
        }
        $authed = $plugin->getAuth($plugconf, $plugopts);
        if (isset($authed))
        {
          return $authed;
        }
      }
    }

    error_log("No auth methods succeeded for $confname.");
    return false;
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
    $validate = $this->get_prop('validate_user',   true);
    if ($validate && is_callable([$this, 'validate_user']))
    {
      $valid = $this->validate_user($user);
      if (isset($valid))
      { // Only process further if it returned a defined result.
        if (!$valid)
        {
          $this->go_err('invalid_user', $login_page);
        }
      }
    }
    $this->set_user($user);
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

  /**
   * Set the user.
   */
  protected function set_user ($user)
  {
    $this->user = $user;
    $this->data['user'] = $user;
    if (isset($user->lang) && $user->lang && is_callable([$this, 'set_lang']))
    {
      $this->set_lang($user->lang);
    }
  }

}
