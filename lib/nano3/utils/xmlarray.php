<?php

namespace Nano3\Utils;

/**
 * SimpleXML <=> PHP Array
 *
 * SimpleXML is great, it's fast, and it has transparency with the DOM
 * classes. However, it's a resource tied to the libxml2 system library,
 * and thus cannot be stored in a session. This library will help resolve that,
 * by converting the array into a form that can be stored safely, and offering
 * a method to restore the object as well.
 */

class XMLArray
{
  public function encode ($xml)
  {
    $array = array
    (
      'name' => $xml->getName(),
      'attr' => array(),
    );
    foreach ($xml->attributes() as $key => $val)
    {
      $array['attr']["$key"] = "$val";
    }
    $text = (string)$xml;
    $text = trim($text);
    if (strlen($text) > 0)
    {
      $array['text'] = $text;
    }
    foreach ($xml->children() as $child)
    {
      if (!isset($array['tree']))
      {
        $array['tree'] = array();
      }
      $array['tree'][] = self::encode($child);
    }
    return $array;
  }

  public function decode ($array, $parent=Null)
  {
    if (!isset($parent))
    {
      $roottag = $array['name'];
      if (isset($array['text']))
      { 
        $text = $array['text'];
        $rootel = "<$roottag>$text</$roottag>";
      }
      else
      {
        $rootel  = "<$roottag/>";
      }
      $xml = new \SimpleXMLElement($rootel);
    }
    else
    {
      if (isset($array['text']))
      {
        $xml = $parent->addChild($array['name'], $array['text']);
      }
      else
      {
        $xml = $parent->addChild($array['name']);
      }
    }

    foreach ($array['attr'] as $key => $val)
    {
      $xml->addAttribute($key, $val);
    }

    if (isset($array['tree']))
    {
      foreach ($array['tree'] as $child)
      {
        self::decode($child, $xml);
      }
    }

    return $xml;
  }

}