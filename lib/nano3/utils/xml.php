<?php

namespace Nano3\Utils;

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
  public function append ($parent, $child)
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
      $parent->ownerDocument->importNode($child, True);
      $parent->appendChild($child);
      return True;
    }
    return False;
  }

}

