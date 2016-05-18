<?php

namespace Nano4\Controllers;

/**
 * Provide a get_auth() method that can use either Authenticated users,
 * or a special header. The get_auth() method will only use Authenticated
 * users if the UserAuth trait is loaded as well, and the configuration has
 * the 'userAccess' option set as true.
 */
trait WebAPI
{
  protected function __construct_webapi_controller ($opts=[])
  {
    $this->set_prop('need_user',     False); // We're not using regular auth.
    $this->set_prop('validate_user', False); // No user validation either.
  }

  protected function get_auth ($opts=[])
  {
    $confname = $this->get_prop('webapi_config', 'webapi');
    
    $nano = \Nano4\get_instance();
    $conf = $nano->conf[$confname];

    if (is_callable([$this, 'setup_webapi_auth']))
    {
      $this->setup_webapi_auth($conf, $opts);
    }

    if (isset($conf['userAccess']) && $conf['userAccess'])
    {
      if (is_callable([$this, 'get_user']))
      {
        $user = $this->get_user(true);
        if ($user)
        {
          if (is_callable([$this, 'validate_api_user']))
          {
            return $this->validate_api_user($user);
          }
          else
          { // Assume  validation is true.
            return true;
          }
        }
      }
    }

    if (isset($conf['authPlugins']))
    {
      foreach ($conf['authPlugins'] as $plugname => $plugconf)
      {
        $classname = "\\Nano4\\Utils\\Auth\\$plugname";
        $plugin = new $classname(['parent'=>$this]);
        $options = $plugin->options($plugconf);
        $plugopts = ['context'=>$opts];
        foreach ($options as $option)
        {
          if ($option == 'context') continue; // sanity check.
          $value = $this->get_prop("webapi_$option");
          if (isset($value))
          {
            $plugopts[$option] = $value;
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

}