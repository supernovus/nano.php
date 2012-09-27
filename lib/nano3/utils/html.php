<?php

namespace Nano3\Utils;
use Nano3\Exception;

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
   *  'mask'     => boolean   If true, selected value is a bitmask.
   *  'id'       => boolean   If true, and no 'id' attrib exists,
   *                          we set the 'id' for the select to be the same
   *                          as the 'name' attribute.
   *  'ns'       => string    Translation prefix. This is only valid if the
   *                          HTML object has a 'translate' object set.
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

    // Add options, with potential translation processing.
    if ($translate)
    {
      $array = $this->translate->strArray($array, $prefix);
    }
    foreach ($array as $value=>$label)
    {
      if (is_array($label))
      { // Process complex entries.
        if (isset($label[$value_key]))
          $value = $label[$value_key];
        if (isset($label[$label_key]))
          $label = $label[$label_key];
      }

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
   * Build a menu.
   *
   * Given some information and a menu definition, this will
   * generate an HTML menu using a specific layout.
   *
   */
  public function menu ($menu, $opts=array())
  {
    // There are a few accepted menu formats. We detect them
    // automatically as we build the menu.
    // If you use a flat (non-associative) array, then you will
    // need to supply the 'url' and 'name' parameters within the
    // item definition (which is an associative array).
    // Otherwise, if the menu itself is an associate array, the key
    // will be used as the 'url' or 'name' tags if one or both of
    // them is missing, but only if it is a string.
    if (isset($opts['root']))
    {
      $container = $opts['root'];
      if (is_string($container))
      {
        $container = new \SimpleXMLElement($container);
      }
    }
    else
    {
      $container = new \SimpleXMLElement('<div class="menu" />');
    }
    if (isset($opts['itemclass']))
    {
      $itemclass = strtolower($opts['itemclass']);
    }
    else
    {
      $itemclass = Null; // We use a raw <a> tag.
    }

    // Custom rules to show different things.
    if (isset($opts['show']))
    {
      $show_rules = $opts['show'];
    }
    else
    {
      $show_rules = array();
    }

    if (isset($opts['dirlevel']))
    {
      $dirlevel = $opts['dirlevel'];
    }
    else
    {
      $dirlevel = 1;
    }

    if (isset($opts['labels']))
    {
      $menu_labels = $opts['labels'];
    }
    else
    {
      $menu_labels = array();
    }

    // Custom rules to apply styles.
    if (isset($opts['classes']))
    {
      $class_rules = $opts['classes'];
    }
    else
    { // Default set of class rules.
      // Basically, if an item is listed as 'root',
      // it must match the path completely to be listed
      // as selected. Otherwise the first part of the path
      // must be found in the REQUEST_URI.
      $class_rules = array(
        'root' => array(
          'class'   => 'current',
          'path_is' => True,
          'single'  => True,
          'unique'  => True,
        ),
        'current' => array(
          'implicit'   => True,
          'uri_prefix' => $dirlevel,
          'single'     => True,
          'unique'     => True,
        )
      );
    }
    // If we want to keep the default rules and add to them.
    if (isset($opts['addclasses']))
    {
      $class_rules += $opts['addclasses'];
    }
    if (isset($opts['insclasses']))
    {
      $class_rules = $opts['insclasses'] + $class_rules;
    }

    // Storage for unique classes.
    $uniqueclasses = array();

    // Okay, let's do this.
    foreach ($menu as $key => $def)
    {

      // Let's see if there are any filters that apply.
      $filtered = False;
      foreach ($show_rules as $rkey => $rule)
      { 
        // We only perform the checks on defs that have the rule.
        if (isset($def[$rkey]))
        {
          if ($rule instanceof \Closure)
          { // Our closure will return True or False.
            if (!$rule($def, $key))
            { // If we get False, we skip this menu item.
              $filtered = True;
              break;
            }
          }
          else
          { // We check to see if the values match up.
            if ($def[$rkey] != $rule)
            { // Our show rule did not match, skip this menu item.
              $filtered = True;
              break;
            }
          }
        }
      }
      if ($filtered) 
      { // One of the show rules did not match, skip this item.
        continue; 
      }

      // Next, deal with types other than a normal menu item.
      if (isset($def['type']))
      {
        if ($def['type'] == 'label')
        { // Labels.
          if (isset($def['name']))
          {
            $name = $def['name'];
          }
          elseif (is_string($key))
          {
            $name = $key;
          }
          else
          {
            throw new Exception("No name found for label.");
          }
  
          if (isset($menu_labels[$name]))
          {
            if (isset($def['element']))
            {
              $element = $def['element'];
            }
            else
            {
              $element = 'span';
            }
  
            $label = $menu_labels[$name];
  
            if ($label instanceof \Closure)
            {
              $text = $label($def);
            }
            else
            {
              $text = $label;
              if (isset($this->translate))
              {
                $text = $this->translate[$text];
              }
            }
            $container->addChild($element, $text);
          }
        }
        elseif ($def['type'] == 'div')
        {
          if (isset($def['element']))
          {
            $elname = $def['element'];
          }
          else
          {
            $elname = 'span';
          }
          $space = $container->addChild($elname, '&nbsp;');
          if (isset($def['class']))
          {
            $divclass = $def['class'];
          }
          else
          {
            $divclass = 'spacer';
          }
          $space->addAttribute('class', $divclass);
        }
        continue; // We can skip the rest of the code below.
      }

      // Get our target URL.
      if (isset($def['url']))
      {
        $url = $def['url'];
      }
      elseif (is_string($key))
      {
        $url = $key;
      }
      else
      {
        throw new Exception("No URL found for menu item: $key");
      }

      // Our 'path' which may or may not be the same as the URL.
      if (isset($def['path']))
      {
        $path = $def['path'];
      }
      else
      {
        $path = $url;
      }

      // Get our item name/label.
      if (isset($def['name']))
      {
        $name = $def['name'];
      }
      elseif (is_string($key))
      {
        $name = $key;
      }
      else
      {
        throw new Exception("No Name found for menu item: $key");
      }

      // Now, if we're using a translation object, translate.
      // We're using the ArrayAccess interface to the translate object.
      if (isset($this->translate))
      {
        $name = $this->translate[$name];
      }

      // Okay, now let's see if we need to apply any classes.
      $classes = array();
      foreach ($class_rules as $rkey => $rule)
      {
        // First, we only apply rules that our definition has
        // subscribed to, unless the rule is implicit.
        if (!isset($rule['implicit']) || !$rule['implicit'])
        {
          if (!isset($def[$rkey])) { continue; } // Skip this rule.
        }

        // Now we continue to search for paths.
        $matched = False;
        if (isset($rule['class']))
        {
          $class = $rule['class'];
        }
        elseif (is_string($rkey))
        {
          $class = $rkey;
        }
        else
        {
          continue;
        }

        // If the class was registered as unique, skip it.
        if (isset($uniqueclasses[$class])) { continue; }

        // If we already have the class, skip it.
        if (isset($classes[$class])) { continue; }

        // Check for path_is rules, which are the simplest tests.
        if (isset($rule['path_is']))
        {
          if (is_string($rule['path_is']))
          {
            $test_path = $rule['path_is'];
          }
          elseif (isset($_SERVER['PATH_INFO']))
          {
            $test_path = $_SERVER['PATH_INFO'];
          }
          elseif (isset($_SERVER['REQUEST_URI']))
          {
            $test_path = $_SERVER['REQUEST_URI'];
          }
          else
          {
            throw new Exception("Could not determine URI.");
          }
          // Okay, now let's see if we match.
          if ($path == $test_path)
          {
            $classes[$class] = True;
            $matched = True;
          }
        }
        // Next up are uri_prefix rules, which are more complex.
        elseif (isset($rule['uri_prefix']))
        {
          $uripref = $rule['uri_prefix'];
          if (is_array($uripref))
          { // Array format: array($section, $test_path);
            $section   = $uripref[0];
            $test_path = $uripref[1];
          }
          elseif (is_numeric($uripref))
          { // Number is section, use REQUEST_URI for test_path.
            $section = $uripref;
            $test_path = $_SERVER['REQUEST_URI'];
          }
          else
          { // Anything else is the test_path, use 1 as the section.
            $section   = 1;
            $test_path = $uripref;
          }

          // If test_path was a boolean instead of a string,
          // then if it's True, we use PATH_INFO instead of REQUEST_URI.
          if (is_bool($test_path))
          {
            if ($test_path)
            {
              $test_path = $_SERVER['PATH_INFO'];
            }
            else
            {
              $test_path = $_SERVER['REQUEST_URI'];
            }
          }
          
          $pathsplit = explode('/', $path);
          $prefix    = $pathsplit[$section];
          if (!$prefix) { continue; }
#          error_log("testing '$test_path' against '$prefix'");
          if (strpos($test_path, $prefix) !== False)
          {
            $classes[$class] = True;
            $matched = True;
          }
        }
        // Next, we look for custom tests, which are closures.
        elseif (isset($rule['test']) && $rule['test'] instanceof \Closure)
        {
          $test = $rule['test'];
          $value = $test($path, $def, $key);
          if ($value)
          {
            $classes[$class] = True;
            $matched = True;
          }
        }
        else
        { // If no matching rules were specified, we assume truth.
          $classes[$class] = True;
          $matched = True;
        }

        // Okay, now to handle matched rules.
        if ($matched)
        {
          // For single rules, if they matched, we remove them.
          if (isset($rule['single']) && $rule['single'])
          {
            unset($class_rules[$rkey]);
          }
          if (isset($rule['unique']) && $rule['unique'])
          {
            // For unique rules, mark down the class.
            $uniqueclasses[$class] = True;
          }
        }
      }

      // Now build a class string.
      if (count($classes) > 0)
      { // We have classes.
        $class = join(' ', array_keys($classes));
      }
      else
      {
        $class = Null;
      }

      if (isset($itemclass) && $itemclass != 'a')
      { // We're using a custom container. We put an <a/> within it.
        $item = $container->addChild($itemclass);
        if (isset($class))
        {
          $item->addAttribute('class', $class);
        }
        $link = $item->addChild('a', $name);
        $link->addAttribute('href',  $url);
      }
      else
      { // We're using a raw <a/> tag (my preference.)
        $item = $container->addChild('a', $name);
        $item->addAttribute('href', $url);
        if (isset($class))
        {
          $item->addAttribute('class', $class);
        }
      }
    }

    // Okay, now let's see if we have anything to append to the menu.
    if (isset($opts['append']))
    {
      $append_items = $opts['append'];
      foreach ($append_items as $append)
      {
        if (isset($append['element']))
        {
          $element = $append['element'];
        }
        else
        {
          $element = 'span';
        }
        if (isset($append['content']))
        {
          $content = $append['content'];
        }
        else
        {
          $content = Null;
        }
        $appended = $container->addChild($element, $content);
        if (isset($append['attribs']) && is_array($append_attribs))
        {
          foreach ($append['attribs'] as $attrkey => $attrval)
          {
            $appended->addAttribute($attrkey, $attrval);
          }
        }
      }
    }
    if ($container->count() == 0)
    {
      $container->addChild('span', '&nbsp;');
    }
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
    $nano = \Nano3\get_instance();
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

