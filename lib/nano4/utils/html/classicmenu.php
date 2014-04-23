<?php

namespace Nano4\Utils\HTML;

/**
 * The original classic menu generator.
 *
 * This is kept for backwards compatibility. The new RouterMenu is the
 * recommended replacement for this library (but only works if your app is
 * using the Nano Router plugin, whereas this requires no specific routing
 * system at all.)
 */

class ClassicMenu
{
  protected $parent;

  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
      $this->parent = $opts['parent'];
  }

  /**
   * Build a menu.
   *
   * Given some information and a menu definition, this will
   * generate an HTML menu using a specific layout.
   *
   * @param Array  $menu   The menu definition.
   * @param Array  $opts   Options to change the behavior.
   *
   * @return SimpleXMLElement  An HTML structure representing the menu.
   *
   */
  public function buildMenu ($menu, $opts=[])
  {
    // There are a few accepted menu formats. We detect them
    // automatically as we build the menu.
    //
    // If you use a flat (non-associative) array, then you will
    // need to supply the 'url' and 'name' parameters within the
    // item definition (which is an associative array).
    //
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
      $show_rules = [];
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
      $menu_labels = [];
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
      $class_rules = 
      [
        'root' =>
        [
          'class'   => 'current',
          'path_is' => True,
          'single'  => True,
          'unique'  => True,
        ],
        'current' =>
        [
          'implicit'   => True,
          'uri_prefix' => $dirlevel,
          'single'     => True,
          'unique'     => True,
        ]
      ];
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
    $uniqueclasses = [];

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
              if (isset($this->parent, $this->parent->translate))
              {
                $text = $this->parent->translate[$text];
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
      if (isset($this->parent, $this->parent->translate))
      {
        $name = $this->parent->translate[$name];
      }

      // Okay, now let's see if we need to apply any classes.
      $classes = [];
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

    return $container;
  }

}
