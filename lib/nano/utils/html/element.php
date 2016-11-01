<?php

namespace Nano\Utils\HTML;

/**
 * Progmatically create HTML structures using PHP objects.
 */

class Element implements \ArrayAccess
{
  public $xml;     // The SimpleXMLElement representing our value.
  public $parent;  // Our parent Element object (if any.)

  /**
   * Build a new HTML\Element object.
   *
   * @param mixed $xml    The tag name, or initial XML data.
   * @param array $opts   Options for constructing the object.
   *
   * Options:
   * 
   *   'parent'      The parent Element object. If this is set, then
   *                 the $xml parameter must be a simple tag name.
   *
   *   'content'     Used if 'parent' is set, to optionally set the
   *                 body content of the new tag.
   *
   *   'attrs'       If set, this must be a PHP array.
   *                 Associative members are name => value mapping.
   *                 Flat (i.e. numerically indexed) members are boolean
   *                 attributes to set (i.e. selected="selected" style.)
   *
   *  If there is no 'parent' option, then the $xml variable can be in a
   *  few different formats:
   *
   *    A simple tag name                     'h1'
   *    An XML string                         '<ul id="blah"/>'
   *    A SimpleXMLElement object
   *    A DOMElement or DOMDocument object
   *
   */
  public function __construct ($xml, $opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
      $content = Null;
      if (isset($opts['content']))
        $content = $opts['content'];
      $this->xml = $this->parent->xml->addChild($xml, $content);
    }
    else
    {
      if (is_string($xml))
      {
        if (substr(trim($xml), 0, 1) == '<')
        {
          $this->xml = new \SimpleXMLElement($xml);
        }
        else
        {
          $this->xml = new \SimpleXMLElement("<$xml/>");
        }
      }
      elseif ($xml instanceof \SimpleXMLElement)
      {
        $this->xml = $xml;
      }
      elseif ($xml instanceof \DOMNode)
      {
        $this->xml = simplexml_import_dom($xml);
      }
    }

    if (isset($opts['attrs']))
    {
      foreach ($opts['attrs'] as $akey => $aval)
      {
        if (is_numeric($akey))
          $aname = $aval;
        else
          $aname = $akey;

        $this->xml->addAttribute($aname, $aval);
      }
    }
  }

  /**
   * Add an existing HTML or XML element.
   *
   * @param mixed $element     The element we want to add.
   *
   * The $element variable can be one of the following:
   *
   *   Another HTML\Element object.
   *   A SimpleXMLElement object.
   *   A DOMElement or DOMDocument object.
   *   An XML string                           '<ul id="blah"/>'
   *
   */
  public function add ($element)
  {
    if (!($element instanceof Element))
    {
      // Wrap the element in an Element.
      $element = new Element($element);
    }

    if (isset($element->xml) && $element->xml instanceof \SimpleXMLElement)
    {
      \Nano\Utils\XML::append($this->xml, $element->xml);
      return $element;
    }

  }

  /**
   * Create a child HTML element.
   *
   * @param string $tagname    The tag to add.
   * @param array  $params     Content and attributes.
   *
   * This __call function handles the magic of adding new children.
   * Basically, any unknown method is assumed to be a child tag name.
   *
   * The parameters are handled depending on what they are.
   *
   * If a parameter is a string, it's considered body content.
   * Multiple string parameters will be joined using newlines as a separator.
   *
   * If a parameter is an array, it's assumed to be a set of attributes.
   * Multiple arrays will be joined using array addition.
   *
   */
  public function __call ($tag, $params)
  {
    $opts = array('parent'=>$this);
    if (count($params) > 0)
    {
      foreach ($params as $param)
      {
        if (is_array($param))
        {
          if (isset($opts['attrs']))
          {
            $opts['attrs'] += $param;
          }
          else
          {
            $opts['attrs'] = $param;
          }
        }
        else
        {
          if (isset($opts['content']))
          {
            $opts['content'] .= "\n$param";
          }
          else
          {
            $opts['content'] = "$param";
          }
        }
      }
    }
    return new Element($tag, $opts);
  }

  /**
   * Return the XML string representing our element.
   */
  public function to_xml ($opts=array())
  {
    return $this->xml->asXML();
  }

  /**
   * Return the HTML string representing our element.
   *
   * This strips the XML declarator, and trims the string.
   *
   * It can optionally add an HTML doctype declarator.
   * You can specify the declarator (the part after !DOCTYPE) as
   * a string, or use one of the following numeric values:
   *
   *    4.0  HTML 4.01 Transitional
   *   -4.0  HTML 4.01 Strict
   *    1.0  XHTML 1.0 Transitional
   *   -1.0  XHTML 1.0 Strict
   *    1.1  XHTML 1.1
   *   -1.1  XHTML 1.1 (alias)
   *    5.0  HTML 5
   *   -5.0  HTML 5 (alias)
   *
   * The only number that requires a decimal value is 1.1, the rest
   * the decimal place is optional and only included for formatting.
   */
  public function to_html ($opts=array())
  {
    $xml = $this->to_xml($opts);
    $declarator = '';
    if (isset($opts['doctype']))
    {
      $doctype = $opts['doctype'];
      if (is_numeric($doctype))
      {
        if ($doctype >= 5 || $doctype <= -5)
          $doctype = 'html';
        elseif ($doctype >= 4)
          $doctype = 'HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"';
        elseif ($doctype == 1.1 || $doctype == -1.1)
          $doctype = 'html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"';
        elseif ($doctype == 1.0)
          $doctype = 'html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"';
        elseif ($doctype <= -4)
          $doctype = 'HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"';
        elseif ($doctype == -1.0)
          $doctype = 'html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"';
      }

      if (is_string($doctype))
      {
        $declarator = "<!DOCTYPE $doctype>";
      }
    }

    if (isset($opts['xml']) && $opts['xml'])
    {
      $declarator = "\\1$declarator";
    }

    $html = trim(preg_replace('/(<\?xml .*?\?>)\s*/', $declarator, $xml));
    return $html;
  }

  /**
   * If an HTML\Element object is used in a string context, it will
   * return an HTML string, see to_html() for details.
   */
  public function __toString ()
  {
    return $this->to_html();
  }

  /**
   * Is an attribute set on our element?
   */
  public function offsetExists ($offset)
  {
    return isset($this->xml[$offset]);
  }

  /**
   * Get an attribute.
   */
  public function offsetGet ($offset)
  {
    return $this->xml[$offset];
  }

  /**
   * Set an attribute.
   */
  public function offsetSet($offset, $value)
  {
    $this->offsetUnset($offset);
    if (is_bool($value))
    {
      if (!$value) return;
      else $value = $offset;
    } 
    $this->xml->addAttribute($offset, $value);
  }

  /**
   * Unset an attribute.
   */
  public function offsetUnset ($offset)
  {
    if (isset($this->xml[$offset]))
    {
      unset($this->xml[$offset]);
    }
  }

} // End of class Element.
