<?php

namespace Nano4\Controllers;

/**
 * Adds Resource Management for CSS, Stylesheets, etc.
 * Has built-in support for the Nano.js project.
 */

trait Resources
{
  /**
   * Resources represent external files, such as scripts, stylesheets, etc.
   * They are managed through a generic system that allows for easy future
   * expansion.
   *
   * Each group as a resource id, such as 'js', 'css', etc, and the following
   * definitions:
   *
   *   name:      The variable name for templates.
   *   path:      An array of paths to look for resource files in.
   *   exts:      An array of extensions to look for resource files.
   *   groups:    An array of arrays, each being named groups.
   *              It is recommended to prefix groups with an identifier
   *              such as '#'.
   *              We include '#common' and '#webapp' as example groups,
   *              they depend upon the Nano.js library set.
   *   added:     An empty array, will be populated by use_resource();
   *
   *  A group for one resource type MAY in fact depend on a resource of another
   *  type. For instance, you may have a Javascript file that depends on a
   *  CSS stylesheet being loaded. You can define a rule that will include it,
   *  by using a 'type:name' format, such as 'css:foobar'.
   *
   */
  protected $resources =
  [
    'js' =>
    [
      'name' => 'scripts',
      'path' => ['scripts', 'scripts/nano', 'scripts/ext'],
      'exts' => ['.min.js', '.js'],
      'groups' =>
      [ 
        '#common' =>
        [ // The base scripts we expect everywhere.
          'jquery', 
          'json3', 
          'coreutils',
          'json.jq', 
          'disabled.jq',
          'exists.jq',
        ],
        '#webapp' =>
        [ // The Webapp library with Riot.js (to be removed in the future.)
          '#common',
          'riot-core',
          'riot.render',
          'modelapi',
          'webapp',
        ],
        '#webcore' =>
        [ // A simplified web app model core. No rendering engine specified.
          '#common',
          'observable',
          'modelapi',
          'viewcontroller',
        ],
        '#base64' =>
        [ // Base64 encoding and decoding.
          'crypto/components/core-min',
          'crypto/components/enc-base64-min',
        ],
        '#ace' =>
        [ // The Ace editor, it uses an embedded module loader for components.
          'ace/src-min-noconflict/ace'
        ],
        '#editor' => 
        [ // The Nano.Editor component.
          '#common',
          '#ace', 
          '#base64',
          'editor',
        ],
      ],
      'added' => [], 
    ],
    'css' =>
    [
      'name'   => 'stylesheets',
      'path'   => ['style'],
      'exts'   => ['.css'],
      'groups' => [],
      'added'  => [],
    ],
  ];

  /**
   * Find a resource file, based on known paths and extensions.
   *
   * @param String $type    The resource type.
   * @param String $name    The resource name without path or extension.
   */
  public function find_resource ($type, $name)
  {
    if (!isset($this->resources[$type]))
      return Null; 

    $exts = $this->resources[$type]['exts'];
    $path = $this->resources[$type]['path'];

    foreach ($path as $dir)
    {
      foreach ($exts as $ext)
      {
        $filename = $dir . '/' . $name . $ext;
        if (file_exists($filename))
        {
          return $filename;
        }
      }
    }
  }

  /**
   * Add a resource file to an array of resources for use in view templates.
   *
   * @param String $type    The resource type.
   * @param String $name    The resource or group name.
   */
  public function use_resource ($type, $name)
  {
    // Make sure it's a valid resource type.
    if (!isset($this->resources[$type])) return False;

    // Handle array input.
    if (is_array($name))
    {
      foreach ($name as $res)
      {
        $this->use_resource($type, $res);
      }
      return True; // All done.
    }

    if (isset($this->resources[$type]['added'][$name]))
    {
      return True;
    }

    // If this is a group, we process the group members.
    if (isset($this->resources[$type]['groups'][$name]))
    {
      $group = $this->resources[$type]['groups'][$name];
      foreach ($group as $res)
      {
        if (strpos($res, ':') === False)
        {
          $this->use_resource($type, $res);
        }
        else
        {
          $parts = explode(':', $res);
          $etype = $parts[0];
          $ename = $parts[1];
          $this->use_resource($etype, $ename);
        }
      }
      $this->resources[$type]['added'][$name] = $name;
      return True; // We've imported the group, let's leave now.
    }

    $file = $this->find_resource($type, $name);
    if (!isset($file))
    {
      error_log("Could not find $type file for: '$name'.");
      return False;
    }

    $resname = $this->resources[$type]['name'];

#    error_log("Adding $type '$name' to $resname as $file");

    if (!isset($this->data[$resname]))
    {
      $this->data[$resname] = array();
    }

    $this->data[$resname][] = $file;
    $this->resources[$type]['added'][$name] = $file;
    return True;
  }

  /**
   * Reset a resource group.
   */
  public function reset_resource ($type)
  {
    if (!isset($this->resources[$type])) return false;
    $resname = $this->resources[$type]['name'];
    unset($this->data[$resname]);
    $this->resources[$type]['added'] = [];
  }

  /**
   * Add a Javascript file or group to our used resources.
   */
  public function add_js ($name)
  {
    return $this->use_resource('js', $name);
  }

  /**
   * Add a CSS stylesheet file or group to our used resources.
   */
  public function add_css ($name)
  {
    return $this->use_resource('css', $name);
  }

}

