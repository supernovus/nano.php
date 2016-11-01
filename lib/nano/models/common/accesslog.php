<?php

namespace Nano\Models\Common;

trait AccessLog
{
  abstract public function newChild ($data=[], $opts=[]);

  protected $filters =
  [
    'request' => ['pass', 'newpass', 'confpass'], // Common password fields.
    'headers' => ['HTTP_COOKIE'],                 // No cookies please.
  ];

  protected $context_map = 
  [
    'request'    => 'request_params',
    'pathparams' => 'path_params',
    'body'       => 'body_params',
    'path'       => 'path',
    'method'     => 'method',
  ];

  protected $route_map =
  [
    'name','uri','controller', 'action','strict','redirect','view',
    'view_status','methods','filters'
  ];

  // If we have any JSON parameters in the request data, expand them.
  protected $request_expand_json;

  public function log ($opts)
  {
    if (is_callable([$this, 'pre_log']))
    {
      $this->pre_log($opts);
    }

    if (is_bool($opts))
    {
      $success = $opts;
      $message = null;
      $context = null;
      $user    = null;
    }
    elseif (is_string($opts))
    {
      $success = false;
      $message = $opts;
      $context = null;
      $user    = null;
    }
    elseif (is_array($opts))
    {
      if (isset($opts['success']))
      {
        $success = $opts['success'];
      }
      elseif (isset($opts['message']))
      {
        $success = false;
      }
      else
      {
        $success = true;
      }

      $message = isset($opts['message']) ? $opts['message'] : null;
      $context = isset($opts['context']) ? $opts['context'] : null;
      $user    = isset($opts['user'])    ? $opts['user']    : null;
    }
    
    $logdata = ['success'=>$success];

    if (isset($message))
    {
      $logdata['message'] = $message;
    }

    $logdata['timestamp'] = time();

    $log = $this->newChild($logdata);

    $this->log_headers($log);

    // Add context information if it is included.
    if (isset($context) && $context instanceof \Nano\Plugins\RouteContext)
    {
      $this->log_context($log, $context);
    }

    // Add user information if it is included.
    if (isset($user) && is_object($user))
    {
      $this->log_user($log, $user);
    }

    if (is_callable([$this, 'post_log']))
    {
      $this->post_log($log, $opts);
    }

    $log->save();
  }

  protected function log_headers ($log)
  {
    // Add server headers.
    $headers = $_SERVER;
    if (isset($this->filters['headers']))
    {
      foreach ($this->filters['headers'] as $filter)
      {
        unset($headers[$filter]);
      }
    }
    $log->headers = $headers;
  }

  protected function log_context ($log, $context)
  {
    $cdata = [];
    
    foreach ($this->context_map as $ckey => $cprop)
    {
      $cval = $context->$cprop;
      if (is_array($cval) && isset($this->filters[$ckey]))
      {
        foreach ($this->filters[$ckey] as $filter)
        {
          unset($cval[$filter]);
        }
      }
      if ($ckey == 'request' && isset($this->request_expand_json))
      {
        foreach ($this->request_expand_json as $varname)
        {
          if (isset($cval[$varname]) && is_string($cval[$varname]))
          {
            $cval[$varname] = json_decode($cval[$varname], true);
          }
        }
      }
      $cdata[$ckey] = $cval;
    }

    if (isset($context->route))
    {
      $route = $context->route;
      $rdata = [];
      foreach ($this->route_map as $rkey => $rprop)
      {
        if (is_numeric($rkey))
          $rkey = $rprop;
        if (isset($route->$rprop))
          $rdata[$rkey] = $route->$rprop;
      }
      if (isset($rdata['redirect']))
      { // Only include this information if there is a redirect.
        $rdata['redirect_is_route'] = $context->redirect_is_route;
      }
  
      $cdata['route'] = $rdata;

      $cdata['base_uri'] = $context->router->base_uri;
    }
    
    $log->context = $cdata;
  }

  protected function log_user ($log, $user)
  {
    $userdata = [];
    if (isset($user->id))
      $userdata['id'] = $user->id;
    elseif (isset($user->_id))
      $userdata['id'] = $user->_id;
    if (isset($user->email))
      $userdata['email'] = $user->email;
    if (isset($user->name))
      $userdata['name'] = $user->name;
    $log->userdata = $userdata;
  } 
}

interface AccessRecord
{
  public function save ($opts=[]);
}

