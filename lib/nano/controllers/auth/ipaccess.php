<?php

namespace Nano\Controllers\Auth;

class IPAccess extends Plugin
{
  public function options ($conf)
  {
    return ['params','header'];
  }
  public function getAuth ($conf, $opts=[])
  {
    $context = isset($opts['context']) ? $opts['context'] : null;
    $params  = isset($opts['params'])  ? $opts['params']  : null;
    $header  = isset($opts['header'])  ? $opts['header']  : 'X-Nano-Auth';

    if (is_array($conf) && isset($conf['ips']) && is_array($conf['ips']))
    { // See if the remote IP is in our trusted list.
      $ip = $_SERVER['REMOTE_ADDR'];
      if (isset($conf['ips'][$ip]))
      { // Get the API access configuration.
        $useAuthHeader = $conf['ips'][$ip];
        if (is_bool($useAuthHeader) && !$useAuthHeader)
        { // It's set to false, no further authentication done.
          return true;
        }
        // Enable the getallheaders() function if it doesn't exist.
        $nano = \Nano\get_instance();
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
            if (!isset($context))
            {
              error_log("No context sent to IPAccess plugin.");
              return false;
            }
            foreach ($params as $param)
            {
              if (isset($context[$param]))
              {
                $vals[] = trim($context[$param]);
              }
              else
              { // Missing a required parameter.
                error_log("API parameter '$param' missing.");
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
            error_log("The $header header did not match expected result.\nexpected: $hash\ngot:$authHeader\nvals:".json_encode($vals));
            return false;
          }
        }
        else
        { // No header, skip it.
#          error_log("No $header header found in: ".json_encode($headers));
          return;
        }
      }
      else
      {
        error_log("Remote IP '$ip' was not in access list.");
        return;
      }
    }
  }
}
