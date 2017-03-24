<?php

namespace Nano\Controllers;

function set_json_property (&$data, $pname, $pval)
{
  if (is_array($data))
  {
    $data[$pname] = $pval;
  }
  elseif (is_object($data))
  {
    $data->$pname = $pval;
  }
}

function get_json_property ($data, $pname)
{
  if (is_array($data) && isset($data[$pname]))
  {
    return $data[$pname];
  }
  elseif (is_object($data) && isset($data->$pname))
  {
    return $data->$pname;
  }
}

function has_json_property ($data, $pname)
{
  if (is_array($data) && isset($data[$pname]))
    return true;
  elseif (is_object($data) && isset($data->$pname))
    return true;
  return false;
}

trait JsonResponse
{
  public function json_msg ($data=[], $opts=[])
  {
    if (!has_json_property($data, 'version'))
    {
      if (property_exists($this, 'api_version') && isset($this->api_version))
      {
        set_json_property($data, 'version', $this->api_version);
      }
    }
    if (!has_json_property($data, 'session_id'))
    {
      if (property_exists($this, 'session_id') && isset($this->session_id))
      {
        set_json_property($data, 'session_id', $this->session_id);
      }
    }
    return $this->send_json($data, $opts);
  }

  public function json_ok ($data=[], $opts=[])
  {
    set_json_property($data, 'success', true);
    return $this->json_msg($data, $opts);
  }

  public function json_err ($errors, $data=[], $opts=[])
  {
    if (!is_array($errors))
    {
      $errors = [$errors];
    }
    set_json_property($data, 'success', false);
    set_json_property($data, 'errors',  $errors);
    return $this->json_msg($data, $opts);
  }
}
