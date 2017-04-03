<?php

namespace Nano\Controllers;

function set_xml_attr ($data, $pname, $pval)
{
  if ($data instanceof \SimpleXMLElement)
  {
    $data[$pname] = $pval;
  }
  elseif ($data instanceof \DOMElement)
  {
    $data->setAttribute($pname, $pval);
  }
  elseif ($data instanceof \DOMDocument)
  {
    $data->documentElement->setAttribute($pname, $pval);
  }
  else
  {
    throw new \Exception("Invalid XML sent to set_xml_attr()");
  }
}

function get_xml_attr ($data, $pname)
{
  if ($data instanceof \SimpleXMLElement)
  {
    return (string)$data[$pname];
  }
  elseif ($data instanceof \DOMElement)
  {
    return $data->getAttribute($pname);
  }
  elseif ($data instanceof \DOMDocument)
  {
    return $data->documentElement->getAttribute($pname);
  }
  else
  {
    throw new \Exception("Invalid XML sent to get_xml_attr()");
  }
}

function has_xml_attr ($data, $pname)
{
  if ($data instanceof \SimpleXMLElement)
  {
    return isset($data[$pname]);
  }
  elseif ($data instanceof \DOMElement)
  {
    return $data->hasAttribute($pname);
  }
  elseif ($data instanceof \DOMDocument)
  {
    return $data->documentElement->hasAttribute($pname);
  }
  else
  {
    throw new \Exception("Invalid XML sent to has_xml_attr()");
  }
}

function del_xml_attr ($data, $pname)
{
  if ($data instanceof \SimpleXMLElement)
  {
    unset($data[$pname]);
  }
  elseif ($data instanceof \DOMElement)
  {
    $data->removeAttribute($pname);
  }
  elseif ($data instanceof \DOMDocument)
  {
    $data->documentElement->removeAttribute($pname);
  }
  else
  {
    throw new \Exception("Invalid XML sent to del_xml_attr()");
  }
}

trait XMLResponse
{
  public function xml_msg ($data, $opts=[])
  {
    if (!has_xml_attr($data, 'version'))
    {
      if (property_exists($this, 'api_version') && isset($this->api_version))
      {
        set_xml_attr($data, 'version', $this->api_version);
      }
    }
    if (!has_json_property($data, 'session_id'))
    {
      if (property_exists($this, 'session_id') && isset($this->session_id))
      {
        set_xml_attr($data, 'session_id', $this->session_id);
      }
    }
    return $this->send_xml($data, $opts);
  }

  public function xml_ok ($data=null, $opts=[])
  { // Boolean attribute.
    if (is_null($data))
    {
      $data = new \SimpleXMLElement('<response/>');
    }
    set_xml_attr($data, 'success', 'success');
    return $this->xml_msg($data, $opts);
  }

  public function xml_err ($errors, $data=null, $opts=[])
  {
    if (!is_array($errors))
    {
      $errors = [$errors];
    }
    if (is_null($data))
    {
      $data = new \SimpleXMLElement('<response/>');
    }
    if (has_xml_attr($data, 'success'))
    {
      del_xml_attr($data, 'success');
    }
    if ($data instanceof \DOMNode)
    { // Convert to SimpleXML.
      $data = simplexml_import_dom($data);
    }
    if ($data instanceof \SimpleXMLElement)
    {
      $errNode = $data->addChild('errors');
      foreach ($errors as $errmsg)
      {
        $errNode->addChild('error', $errmsg);
      }
    }
    else
    {
      throw new \Exception("invalid XML sent to xml_err()");
    }
    return $this->xml_msg($data, $opts);
  }
}
