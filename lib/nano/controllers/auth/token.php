<?php

namespace Nano\Controllers\Auth;

class Token extends Plugin
{
  public options ($conf)
  {
    return ['header','model'];
  }

  public function getAuth ($conf, $opts=[])
  {
    $context = isset($opts['context']) ? $opts['context'] : null;
    $header  = isset($opts['header'])  ? $opts['header']  : 'X-Nano-Auth-Token';
    $model   = isset($opts['model'])   ? $opts['model']   : 'auth_tokens';

    if (!isset($model))
    {
      error_log("No model set, cannot continue.");
      return false;
    }

    if (is_string($model))
    {
      $model = $this->parent->model($model);
    }

    $nano = \Nano\get_instance();
    $nano->pragmas->getallheaders;
    $headers = \getallheaders();
    if (isset($headers[$header]))
    {
      $header = $headers[$header];
      $user = $model->getUser($header);
      if (isset($user))
      {
        $this->set_user($user);
        return true;
      }
      elseif (isset($model->errors))
      {
        foreach ($model->errors as $error)
        {
          $this->parent->auth_errors[] = $error;
        }
      }
    }
    return false;
  }
}
