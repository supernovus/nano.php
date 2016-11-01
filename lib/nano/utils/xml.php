<?php

namespace Nano\Utils;

/**
 * Common XML Functions.
 *
 * I like SimpleXML, it's, well, simple. Sometimes however, it's too simple
 * and I need some DOM-juice to turn it up a notch. Well, this is a collection
 * of common functions that may need that extra power.
 */

class XML
{
  /**
   * Append one node to another.
   *
   * @param   object $parent    A SimpleXMLElement or DOMElement object.
   * @param   object $child     A SimpleXMLElement or DOMElement to append.
   * @return  bool              Returns false if invalid params were passed.
   */
  public static function append ($parent, $child)
  {
    if ($parent instanceof \SimpleXMLElement)
    {
      $parent = dom_import_simplexml($parent);
    }
    if ($child instanceof \SimpleXMLElement)
    {
      $child = dom_import_simplexml($child);
    }

    if ($parent instanceof \DOMElement && $child instanceof \DOMElement)
    {
      $child = $parent->ownerDocument->importNode($child, True);
      $parent->appendChild($child);
      return True;
    }
    return False;
  }

  /**
   * Append a text node to an element.
   *
   * @param  object  $parent    A SimpleXMLElement or DOMElement object.
   * @param  string  $text      The text to append as a DOMTextNode.
   * @return bool               Returns false if invalid params were passed.
   */
  public static function appendText ($parent, $text)
  {
    if ($parent instanceof \SimpleXMLElement)
    {
      $parent = dom_import_simplexml($parent);
    }

    if ($parent instanceof \DOMElement)
    {
      $textnode = $parent->ownerDocument->createTextNode($text);
      $parent->appendChild($textnode);
      return True;
    }
    return False;
  }

  /**
   * Rename an element.
   */
  public static function renameElement ($oldNode, $newName, $opts=[])
  {
    if ($oldNode instanceof \SimpleXMLElement)
    {
      $oldNode = dom_import_simplexml($oldNode);
      $returnSimple = true;
    }
    elseif ($oldNode instanceof DOMNode)
    {
      $returnSimple = false;
    }
    else
    {
      return;
    }

    if (isset($opts['clone']) && $opts['clone'])
    {
      $oldNode = $oldNode->cloneNode(true);
    }

    if (isset($opts['ns']))
    {
      $newNS = $opts['ns'];
      $newNode = $oldNode->ownerDocument->createElementNS($newNS, $newName);
    }
    else
    {
      $newNode = $oldNode->ownerDocument->createElement($newName);
    }

    foreach ($oldNode->attributes as $attr)
    {
      $newNode->appendChild($attr->cloneNode());
    }
    foreach ($oldNode->childNodes as $child)
    {
      $newNode->appendChild($child->cloneNode(true));
    }

    if (!isset($opts['replace']) || $opts['replace'])
    {
      $oldNode->parentNode->replaceChild($newNode, $oldNode);
    }

    if ($returnSimple)
    {
      return simplexml_import_dom($newNode);
    }
    else
    {
      return $newNode;
    }
  }

}

