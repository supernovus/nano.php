<?php

namespace Nano4\Utils;
use Nano4\Exception;

class InvalidXMLException extends Exception
{
  protected $message = "Invalid XML object type.";
}

/**
 * Generate common HTML structures.
 */
class HTML
{
  /**
   * The Nano namespace where we'll load component views from.
   * @var string
   */
  public $include_ns;

  /**
   * If set, this should be a Translation object.
   * @var object
   */
  public $translate;

  /**
   * A cache for sub-libraries.
   */
  protected $libcache = [];

  /**
   * Build a new HTML helper object.
   *
   * @param array $opts  Reset the default 'output' and 'include' values.
   */
  public function __construct ($opts=array())
  {
    if (isset($opts['include']))
      $this->include_ns = $opts['include'];
    if (isset($opts['translate']))
      $this->translate = $opts['translate'];
  }

  /**
   * Protected function that handles return values.
   *
   * We previously supported a bunch of different output formats.
   * Now we offer two. The default is to return the HTML string.
   * If you pass 'raw' => true to the opts, we will return the
   * raw object. Also, if the value passed is not a SimpleXML
   * element, we will return it as if 'raw' had been passed.
   *
   * @param mixed $value  A SimpleXML object, or a string.
   * @param array $opts   Options controlling the output.
   * @returns mixed       Output depends on the options sent.
   */
  protected function return_value ($value, $opts)
  {
    if ($value instanceof \SimpleXMLElement && !isset($opts['raw']))
    {
      $string = $value->asXML();
      $string = preg_replace('/<\?xml .*?\?>\s*/', '', $string);
      return $string;
    }
    else
    {
      return $value;
    }
  }

  /**
   * Generate an HTML Select structure.
   *
   * Generates a <select/> structure containing <option/>
   * elements for each item in an associative array, where the 
   * array key is the HTML value, and the array value is the option label.
   * 
   * @param mixed $attrs  HTML attributes for the select tag.
   * @param array $array  Array of Options, where $value => $label.
   * @param array $opts   Optional function-specific settings, see below.
   *
   * The $attrs can either be an associative array of HTML attributes for
   * the select tag, or can be the value of the 'name' attribute.
   *
   * The $opts array may contain several options on top of the standard
   * output options:
   *
   *  'selected' => mixed     The current selected value.
   *
   *  'mask'     => boolean   If true, selected value is a bitmask.
   *
   *  'id'       => boolean   If true, and no 'id' attrib exists,
   *                          we set the 'id' for the select to be the same
   *                          as the 'name' attribute.
   *
   *  'ns'       => string    Translation prefix. This is only valid if the
   *                          HTML object has a 'translate' object set.
   *
   *  'ttns'     => string    Tooltip translation prefix. If you set this,
   *                          and the HTML object has a 'translate' object,
   *                          tooltips can be added to your options.
   *
   * @returns mixed   Output depends on the options.
   */
  public function select ($attrs, $array, $opts=array())
  { // Let's build our select structure.
    $select = new \SimpleXMLElement('<select/>');
    if (is_string($attrs))
    {
      $attrs = array('name'=>$attrs);
    }
    if 
    (
      isset($opts['id']) && $opts['id'] 
      && !isset($attrs['id']) && isset($attrs['name'])
    )
    {
      $attrs['id'] = $attrs['name'];
    }

    // See if we're using bitmasks for the selected values.
    if (isset($opts['mask']))
      $is_mask = $opts['mask'];
    else
      $is_mask = False;

    // Check for a selected option.
    if (isset($opts['selected']))
    {
      $selected = $opts['selected'];

      if (is_array($selected))
      {
        // Get an identifier we can use.
        if (isset($attrs['id']))
        {
          $identifier = $attrs['id'];
        }
        elseif (isset($attrs['name']))
        {
          $identifier = $attrs['name'];
        }
        else
        {
          $identifier = Null;
        }
        if (isset($identifier) && isset($selected[$identifier]))
        {
          $selected = $selected[$identifier];
        }
      }
    }
    else
    {
      $selected = Null;
    }

    // Check for a 'ns' option to override translation prefix.
    if (isset($opts['ns']))
    {
      $prefix = $opts['ns'];
    }
    else
    {
      $prefix = '';
    }

    // Check for a 'ttns' option, specifying a tooltip translation prefix.
    if (isset($opts['ttns']))
    {
      $ttns = $opts['ttns'];
    }
    else
    {
      $ttns = null;
    }

    // For processing complex entities.
    if (isset($opts['labelkey']))
    {
      $label_key = $opts['labelkey'];
    }
    else
    {
      $label_key = 'text';
    }
    if (isset($opts['valuekey']))
    {
      $value_key = $opts['valuekey'];
    }
    else
    {
      $value_key = 'id';
    }

    // Add attributes.
    foreach ($attrs as $aname=>$aval)
    {
      $select->addAttribute($aname, $aval);
    }

    if (isset($opts['translate']))
    {
      $translate = $opts['translate'] && isset($this->translate);
    }
    else
    {
      $translate = isset($this->translate);
    }

    // Used only if translation service is enabled.
    $tooltips = [];

    // Add options, with potential translation processing.
    if ($translate)
    {
      $array = $this->translate->strArray($array, $prefix);
      if (isset($ttns))
      {
        $tooltips = $this->translate->strArray($array, $ttns);
      }
    }
    foreach ($array as $value=>$label)
    {
      if (isset($tooltips[$value]) && $tooltips[$value] != $value)
      {
        $tooltip = $tooltips[$value];
      }
      else
      {
        $tooltip = null;
      }

      if (is_array($label))
      { // Process complex entries.
        if (isset($label[$value_key]))
          $value = $label[$value_key];
        if (isset($label[$label_key]))
          $label = $label[$label_key];
      }

      $option = $select->addChild('option', $label);
      $option->addAttribute('value', $value);
      if (isset($tooltip))
      {
        $option->addAttribute('title', $tooltip);
      }
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
    $html = $this->return_value($select, $opts);
    if (substr(trim($html), -2) == '/>')
    {
      $html = substr_replace(trim($html), '></select>', -2);
#      error_log("Correcting singleton select HTML: $html");
    }
    return $html;
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
      $ul = new \SimpleXMLElement("<$type/>");
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
   * In addition to the usual output options, this also
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
    if (is_object($struct) && is_callable([$struct, 'to_array']))
    {
      $struct = $struct->to_array($opts);
    }
    $json = json_encode($struct);
    $input = new \SimpleXMLElement('<input/>');
    $input->addAttribute('type',   'hidden');
    $input->addAttribute('id',     $name);
    $input->addAttribute('name',   $name);
    $input->addAttribute('value',  $json);
    return $this->return_value($input, $opts);
  }

  /**
   * Build a button.
   */
  public function button ($attrs=array(), $opts=array(), $map=array())
  {
    if (isset($opts['def']))
    {
      $defAttr = $opts['def'];
    }
    else
    {
      $defAttr = 'id';
    }
    if (isset($opts['deftype']))
    {
      $defType = $opts['deftype'];
    }
    else
    {
      $defType = 'button';
    }
    if (is_string($attrs))
    {
      $attrs = array($defAttr=>$attrs, 'type'=>$defType);
    }
    elseif (!isset($attrs[$defAttr]))
    {
      throw new Exception("Cannot continue without primary field.");
    }
    elseif (!isset($attrs['type']))
    {
      $attrs['type'] = $defType;
    }

    // Add any fields specified in the options.
    if (isset($opts['add']) && is_array($opts['add']))
    {
      $attrs += $opts['add'];
    }

    // Map missing fields to other existing fields.
    if (isset($opts['map']))
    { $map = $opts['map'];
      if (is_bool($map) && $map)
      { // A default map for name to id and back again.
        $map = array('id'=>'name','name'=>'id');
      }
      if (is_array($map))
      {
        foreach ($map as $target => $source)
        { 
          if (!isset($attrs[$target]) && isset($attrs[$source]))
          {
            $attrs[$target] = $attrs[$source];
          }
        }
      }
    }

    // Create our object.
    $input = new \SimpleXMLElement('<input/>');

    // Now some automated stuff based on our translation framework.
    if (isset($this->translate))
    { // First, if we have no value, let's get it from the translations.
      if (!isset($attrs['value']))
      {
        if (isset($opts['text_ns']))
        {
          $prefix = $opts['text_ns'];
        }
        else
        {
          $prefix = '';
        }
        $name = $attrs[$defAttr];
        $attrs['value'] = $this->translate[$prefix.$name];
      }
      // Next, if we have no title, and there is a tooltip prefix,
      // let's see if there is a tooltip for us.
      if (!isset($attrs['title']) && isset($opts['tooltip_ns']))
      {
        $prefix = $opts['tooltip_ns'];
        $name = $attrs[$defAttr];
        $tooltip = $this->translate[$prefix.$name];
        if ($tooltip != $prefix.$name)
        {
          $attrs['title'] = $tooltip;
        }
      }
    }
    foreach ($attrs as $name => $value)
    {
      $input->addAttribute($name, $value);
    }
    return $this->return_value($input, $opts);
  }

  /**
   * Build a submit button.
   */
  public function submit ($attrs=array(), $opts=array())
  {
    $opts['def'] = 'name';
    $opts['deftype'] = 'submit';
    return $this->button($attrs, $opts);
  }

  /**
   * Build a menu, using the ClassicMenu library.
   */
  public function menu ($menu, $opts=[])
  {
    if (isset($this->libcache['cmenu']))
      $lib = $this->libcache['cmenu'];
    else
      $lib = $this->libcache['cmenu'] = new HTML\ClassicMenu(['parent'=>$this]);

    $container = $lib->buildMenu($menu, $opts);
    return $this->return_value($container, $opts);
  }

  /**
   * Build a menu, using the RouterMenu library.
   */
  public function routemenu ($menu, $context, $opts=[])
  {
    if (isset($this->libcache['rmenu']))
      $lib = $this->libcache['rmenu'];
    else
      $lib = $this->libcache['rmenu'] = new HTML\RouterMenu(['parent'=>$this]);

    $container = $lib->buildMenu($menu, $context, $opts);
    return $this->return_value($container, $opts);
  }

  // A method to include another Nano view.
  public function get ($view, $data=array())
  {
    if (!isset($this->include_ns))
    {
      throw new Exception("Attempt to use include() with no namespace.");
    }
    $ns = $this->include_ns;
    $nano = \Nano4\get_instance();
    if (!isset($nano->lib[$ns]))
    {
      throw new Exception("No such Nano namespace: $ns");
    }
    $content = $nano->lib[$ns]->load($view, $data);
    return $content;
  }

  // Another way to call get().
  public function __call ($method, $params)
  {
    if (count($params)>0)
    {
      $data = $params[0];
    }
    else
    {
      $data = array();
    }
    return $this->get($method, $data);
  }

}

