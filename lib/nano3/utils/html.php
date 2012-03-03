<?php
/**
 * Generate common HTML structures.
 */

namespace Nano3\Utils;

class HTML
{
  /**
   * Should we echo directly?
   * @var boolean
   */
  public $echo   = False;

  /**
   * Return the SimpleXML element directly?
   * This overrides the $echo attribute.
   * @var boolean
   */
  public $xmlout = False;

  /**
   * Build a new HTML helper object.
   *
   * @param array $opts  Reset the default values of 'echo' and 'xmlout'.
   */
  public function __construct ($opts=array())
  {
    if (isset($opts['echo']))
      $this->echo = $opts['echo'];
    if (isset($opts['xmlout']))
      $this->xmlout = $opts['xmlout'];
  }

  /**
   * Protected function that handles return values.
   *
   * Depending on either the global 'echo' and 'xmlout' variables,
   * or the options of the same name passed to a method, we output
   * either a SimpleXML object, an XML string, or we echo the string
   * directly to the current output stream.
   *
   * @param mixed $value  Either a SimpleXML object, or an array of them.
   * @param array $opts   Override the 'echo' or 'xmlout' settings.
   * @returns mixed       Output depends on the 'echo' and 'xmlout' settings.
   */
  protected function return_value ($value, $opts)
  {
    if (isset($opts['echo']))
      $echo = $opts['echo'];
    else
      $echo = $this->echo;
    if (isset($opts['xmlout']))
      $xmlout = $opts['xmlout'];
    else
      $xmlout = $this->xmlout;

    if ($xmlout)
    { // Return the raw SimpleXML or array of SimpleXML object(s).
      return $value;
    }
    elseif ($echo)
    { // Echo the values directly.
      if (is_array($value))
      {
        foreach ($value as $xml)
        {
          echo $xml->asXML();
        }
      }
      else
      {
        echo $value->asXML();
      }
    }
    else
    { // Return a string representing the XML.
      if (is_array($value))
      {
        $output = '';
        foreach ($value as $xml)
        {
          $output .= $xml->asXML();
        }
        return $output;
      }
      else
      {
        return $value->asXML();
      }
    }
  }

  /**
   * Generate an HTML Select structure.
   *
   * Generates a <select/> structure containing <option/>
   * elements for each item in an associative array, where the 
   * array key is the HTML value, and the array value is the option label.
   * 
   * @param array $attrs  HTML attributes for the select tag.
   * @param array $array  Assoc. Array of Options, where $value => $label.
   * @param array $opts   Optional function-specific settings, see below.
   *
   * The $opts array may contain several options on top of the standard
   * 'echo' and 'xmlout' options:
   *
   *  'selected' => mixed     The current selected value.
   *  'mask'     => boolean   If true, selected value is a bitmask.
   *
   * @returns mixed   Output depends on the 'echo' and 'xmlout' options.
   */
  public function select ($attrs, $array, $opts=array())
  {
    if (isset($opts['selected']))
      $selected = $opts['selected'];
    else
      $selected = Null;

    if (isset($opts['mask']))
      $is_mask = $opts['mask'];
    else
      $is_mask = False;

    $select = new SimpleXMLElement('<select/>');
    foreach ($attrs as $aname=>$aval)
    {
      $select->addAttribute($aname, $aval);
    }
    foreach ($array as $value=>$label)
    {
      $option = $select->addChild('option', $label);
      $option->addAttribute('value', $value);
      if 
      (
        isset($selected)
        &&
        (
          ($is_mask && ($value & $selected))
          ||
          ($value == $selected)
        )
      )
      {
        $option->addAttribute('selected', 'selected');
      }
    }
    return $this->return_value($select, $opts);
  }

  // Backend function that powers the ul()/ol() methods.
  protected function build_list ($menu, $type, $parent=Null)
  {
    if (isset($parent))
    {
      $ul = $parent->addChild($type);
    }
    else
    {
      $ul = new SimpleXMLElement("<$type/>");
    }
    $li = Null; // This will point at the last known <li/> item.
    foreach ($menu as $index=>$value)
    {
      if (is_numeric($index))
      { // No associative key.
        if (is_array($value))
        { // A raw array with no label is attributes.
          foreach ($value as $key=>$attr)
          {
            if (isset($li))
            {
              $li->addAttribute($key, $attr);
            }
            else
            {
              $ul->addAttribute($key, $attr);
            }
          }
        }
        else
        { // A static text value.
          $li = $ul->addChild('li', $value);
        }
      }
      else
      { // An associative key was supplied.
        if (is_array($value))
        { // A sub-menu, following the same rules as the top menu.
          $li = $ul->addChild('li');
          $li->addChild('label', $index);
          $this->build_list($value, $type, $li);
        }
        else
        { // Assume the value is the 'class' attribute.
          $li = $ul->addChild('li', $index);
          $li->addAttribute('class', $value);
        }
      }
    }
    return $ul;
  }

  /**
   * Generate a recursive list (<ul/>).
   *
   * Creates a <ul/> object with appropriate nesting.
   *
   * @param array  $menu  The array representing the menu.
   * @param array  $opts  Optional function-specific settings.
   *
   * In addition to the usual 'echo' and 'xmlout' options, this also
   * supports the following option:
   *
   *  'type'  => string       The list type, defaults to 'ul'.
   *
   * The array representing the menu can be quite complex.
   *
   * Flat members (i.e. ones where you did not specify the key)
   * that are strings are considered text items for the list.
   *
   * Flat members that are arrays are considered attributes.
   * If no other list item has been defined yet, the attributes will
   * be applied to the top-level list element, otherwise they will be
   * applied to the last defined list item.
   *
   * Named members (i.e. ones where you specify a named key)
   * that have array values are considered sub-menus, and the same
   * rules apply to that array as this one.
   *
   * Named members with string values will set the 'class' attribute
   * on the list item to the value of the string.
   *
   */
  public function ul ($menu, $opts=array())
  {
    if (isset($opts['type']))
      $type = $opts['type'];
    else
      $type = 'ul';

    $list = $this->build_list($menu, $type);

    return $this->return_value($list, $opts);
  }

  /**
   * Generate a recursive list (<ol/>).
   *
   * Same as the ul() method, but defaults to 'ol' for the type.
   */
  public function ol ($menu, $opts=array())
  {
    if (!isset($opts['type']))
      $opts['type'] = 'ol';

    return $this->ul($menu, $opts);
  }

  /**
   * Generate a hidden input field representing a JSON object.
   *
   * @param string $name    The name/id of the field.
   * @param mixed  $struct  Value to be passed through json_encode().
   * @param array  $opts    Optional, passed to return_value().
   */
  public function json ($name, $struct, $opts=array())
  {
    $json = json_encode($struct);
    $input = new SimpleXMLElement('<input/>');
    $input->addAttribute('type',   'hidden');
    $input->addAttribute('id',     $name);
    $input->addAttribute('name',   $name);
    $input->addAttribute('value',  $json);
    return $this->return_value($input, $opts);
  }

}

