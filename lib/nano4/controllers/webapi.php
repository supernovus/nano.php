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
    $this->set_prop('save_uri',      False); // We don't want to save the URI.
    $this->set_prop('validate_user', False); // No user validation either.
  }

  protected function get_auth ($opts=[])
  {
    $confname = $this->get_prop('webapi_config', 'webapi');
    $params   = $this->get_prop('webapi_params', null);
    $header   = $this->get_prop('webapi_header', 'X-Nano-Auth');
    
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

    if (isset($conf['apiAccess']) && is_array($conf['apiAccess']))
    { // See if the remote IP is in our trusted list.
      $ip = $_SERVER['REMOTE_ADDR'];
      if (isset($conf['apiAccess'][$ip]))
      { // Get the API access configuration.
        $useAuthHeader = $conf['apiAccess'][$ip];
        if (is_bool($useAuthHeader) && !$useAuthHeader)
        { // It's set to false, no further authentication done.
          return true;
        }
        // Enable the getallheaders() function if it doesn't exist.
        $nano->pragmas->getallheaders;
        $headers = \getallheaders();
        if (isset($headers[$header]))
        {
          $authHeader = $headers[$header];
          $vals = [];
          if (isset($conf['siteKey']))
          { // A site-wide API key.
            $vals[] = trim($conf['siteKey']);
          }
          if (isset($params) && count($params) > 0)
          {
            foreach ($params as $param)
            {
              if (isset($opts[$param]))
              {
                $vals[] = trim($opts[$param]);
              }
              else
              { // Missing a required parameter.
                error_log("WebAPI parameter '$param' missing.");
                return false;
              }
            }
          }
          $vals[] = trim($ip);
          if (is_string($useAuthHeader))
          { // An API key specific to this IP address.
            $vals[] = trim($useAuthHeader);
          }
          $hash = hash('sha256', join('', $vals));
          if ($authHeader == $hash)
          {
            return true;
          }
          else
          {
            error_log("The $header header did not match expected result.\nexpected: $hash\ngot:$authHeader");
            return false;
          }
        }
        else
        {
          error_log("No $header header found in: ".json_encode($headers));
          return false;
        }
      }
      else
      {
        error_log("Remote IP '$ip' was not in $confname access list.");
        return false;
      }
    }

    error_log("No auth methods succeeded for $confname.");
    return false;
  }

}