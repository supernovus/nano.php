<?php

namespace Nano\Utils;

/**
 * Routing Information Modeling Language
 *
 * A YAML-based format for describing routing information.
 * Can be used for several purposes:
 *
 *  - Generate JSON configuration files for Router plugin.
 *  - Generate friendly HTML documentation for routes (not yet implemented.)
 *
 * In addition to this PHP implementation, I am planning a Node.js one too.
 *
 * It's loosely inspired by RAML.
 */

const RIML_COMMON_PROPS = 
[
  'title','description','controller','method','apiType','authType',
];

const RIML_ROUTE_PROPS =
[
  'name','path','http','virtual','responseSchema','requestSchema','queryParams',
  'headers','tests','defaultRoute','redirect','redirectRoute',
];

const RIML_HTTP_PROPS =
[
  'GET', 'PUT', 'POST', 'DELETE', 'PATCH', 'HEAD'
];

const RIML_ROUTER_PROPS =
[
  'name'          => 'name',
  'controller'    => 'controller',
  'method'        => 'action',
  'http'          => 'methods',
  'path'          => 'uri',
  'redirect'      => 'redirect',
  'redirectRoute' => 'redirect_is_route',
];

trait RimlCommon
{
  /**
   * Human readable title for documentation.
   */
  public $title;
  /**
   * Human readable description for documentation.
   */
  public $description;
  /**
   * Parent object (if applicable.)
   */
  public $parent;
  /**
   * Root RIML object.
   */
  public $root;
  /**
   * Controller name.
   */
  public $controller;
  /**
   * Handler method name.
   */
  public $method;
  /**
   * API type
   *
   *  false   Not used as an API.
   *  'json'  Uses JSON for API calls.
   *  'xml'   Uses XML for API calls.
   */
  public $apiType;
  /**
   * Authentication type
   *
   *  false       Doesn't require authentication.
   *  'user'      Uses SimpleAuth users.
   *  'ipAccess'  Uses ipAccess Authentication plugin.
   *
   * Use an array for multiple types if more than one is supported.
   */
  public $authType;

  /**
   * The routes within this structure.
   */
  protected $routes = [];

  /**
   * Options are defined using properties starting with a dot.
   */
  public $options = [];

  protected function addRoutes ($routes)
  {
    foreach ($routes as $rname => $rdef)
    {
      if (is_null($rdef)) continue;
      if (substr($rname, 0, 1) === '.')
      {
        $oname = substr($rname, 1);
        $this->options[$oname] = $rdef;
        continue;
      }
      $route = new RimlRoute($rname, $rdef, $this);
      $built[] = $route;
      $this->routes[] = $route;
    }
  }

  public function getRoutes ()
  {
    return $this->routes;
  }

  public function hasRoutes ()
  {
    return (count($this->routes) > 0);
  }

}

class RIML
{
  use RimlCommon;

  public $method_prefix = 'handle_';
  public $confdir;

  protected $templates = []; // Templates for later use.

  protected $traits = []; // Traits for later use.

  protected $included = []; // A list of files we've included.

  protected $sources = []; // Source files marked as .includePoly: true

  public $auto_route_names = []; // Automatically generated route names.

  public function __construct ($source)
  {
    if (is_string($source))
    { // Assume it's the filename.
      $source = $this->loadFile($source);
    }
    elseif (is_array($source))
    {
      if (isset($source['dir']))
      {
        $this->confdir = $source['dir'];
      }
      if (isset($source['prefix']))
      {
        $this->method_prefix = $source['prefix'];
      }

      if (isset($source['file']))
      { // The filename was explicitly passed.
        $source = $this->loadFile($source['file']);
      }
      elseif (isset($source['text']))
      { // The RIML text was explicitly passed.
        $source = $this->loadText($source['text']);
      }
      elseif (isset($source['data']))
      { // The data was explicitly passed.
        $source = $source['data'];
      }
      else
      {
        throw new \Exception("Invalid named paramter sent to RIML() constructor.");
      }
    }
    else
    {
      throw new \Exception("Invalid data passed to RIML() constructor.");
    }
    $this->root   = $this;
    foreach (RIML_COMMON_PROPS as $pname)
    {
      if (isset($source[$pname]))
      {
        $this->$pname = $source[$pname];
        unset($source[$pname]);
      }
    }
    $this->addRoutes($source);
  }

  protected function loadFile ($filename)
  {
    if (file_exists($filename))
    {
      if (!isset($this->confdir))
      {
        $this->confdir = dirname($filename);
      }
      $text = file_get_contents($filename);
      return $this->loadText($text);
    }
    throw new \Exception("Invalid filename '$filename' passed to RIML::loadFile()");
  }

  protected function loadText ($text)
  {
    $self = $this;
    return yaml_parse($text, 0, $ndocs,
    [
      '!include' => function ($value, $tag, $flags) use ($self)
      {
        return $self->includeFile($value);
      },
      '!define' => function ($value, $tag, $flags) use ($self)
      {
        return $self->defineMetadata($value);
      },
      '!use' => function ($value, $tag, $flags) use ($self)
      {
        return $self->useMetadata($value);
      },
      '!controller' => function ($value, $tag, $flags)
      {
        if (!is_array($value))
          $value = [];
        $value['.controller'] = true;
        return $value;
      },
      '!method' => function ($value, $tag, $flags)
      {
        if (!is_array($value))
          $value = [];
        $value['.method'] = true;
        return $value;
      },
    ]);
  }

  protected function includeFile ($value)
  {
    if (strpos($value, '/') === false && isset($this->confdir))
    {
      $file = $this->confdir . '/' . $value;
    }
    else
    {
      $file = $value;
    }
    if (isset($this->included[$file]))
    {
      if ($this->included[$file])
        return null;
      elseif (isset($this->sources[$file]))
        return $this->sources[$file];
    }
    $yaml = $this->loadFile($file);
    $mark = true;
    if (isset($yaml) && is_array($yaml))
    { // Included files are assumed to be virtual by default.
      if (!isset($yaml['virtual']))
        $yaml['virtual'] = true;
      if (isset($yaml['.includePoly']) && $yaml['.includePoly'])
      {
        $mark = false;
        $this->sources[$file] = $yaml;
      }
    }
    $this->included[$file] = $mark;
    return $yaml;
  }

  protected function defineMetadata ($data)
  {
//    error_log("defineMetadata(".json_encode($data).")");
    if (is_array($data) && isset($data['.template'], $data['.vars']))
    {
      $name = $data['.template'];
      unset($data['.template']);
      $this->templates[$name] = $data;
    }
    elseif (is_array($data) && isset($data['.trait']))
    {
      $name = $data['.trait'];
      unset($data['.trait']);
      $this->traits[$name] = $data;
    }
  }

  protected function useMetadata ($data)
  {
//    error_log("useMetadata(".json_encode($data).")");
    $this->applyTraits($data, '.templateTraits');
    if (is_array($data) && isset($data['.template']))
    {
      $name = $data['.template'];
      unset($data['.template']);
      if (isset($this->templates[$name]))
      {
        $template = $this->templates[$name];
        $vars = $template['.vars'];
        unset($template['.vars']);
        foreach ($vars as $varname => $varpathspec)
        {
          if (isset($data[$varname]))
          {
            $value = $data[$varname];

            if (is_string($varpathspec))
              $varpathspec = [$varpathspec];

            foreach ($varpathspec as $varpath)
            {
              $varpaths = explode('/', trim($varpath, '/'));
              $tdata = &$template;
              $lastitem = array_pop($varpaths);
              $textitem = null;
              foreach ($varpaths as $vp)
              {
                if (is_array($tdata) && isset($tdata[$vp]))
                {
                  if (is_array($tdata[$vp]))
                  {
                    $tdata = &$vp;
                  }
                  elseif (is_string($tdata[$vp]))
                  {
                    $textitem = $vp;
                    break;
                  }
                }
                else
                {
                  throw new \Exception("Invalid variable path '$varpath'");
                }
              }
              if (isset($textitem))
              {
                $tdata[$textitem] = str_replace($lastitem, $value, $tdata[$textitem]);
              }
              elseif (is_array($tdata) && isset($tdata[$lastitem]))
              {
                $tdata[$lastitem] = $value;
              }
            }
          }
          else
          {
            throw new \Exception("Unfulfilled variable '$varname' in use statement.");
          }
        }
        if (isset($data['.traits']))
        { // Local traits are added to template traits.
          if (!isset($template['.traits']))
            $template['.traits'] = $data['.traits'];
          else
          { // Merge local traits and template traits.
            if (is_string($template['.traits']))
              $template['.traits'] = [$template['.traits']];
            if (is_string($data['.traits']))
              $data['.traits'] = [$data['.traits']];
            foreach ($data['.traits'] as $tname)
              $template['.traits'][] = $tname;
          }
        }
        $data = $template;
      }
      else
      {
        throw new \Exception("Template $name not found.");
      }
    }
    $this->applyTraits($data);
    return $data;
  }

  protected function applyTraits (&$data, $tprop='.traits')
  {
    if (is_array($data) && isset($data[$tprop]))
    {
      $traits = $data[$tprop];
      unset($data[$tprop]);
      if (!is_array($traits))
        $traits = [$traits];
      foreach ($traits as $tname)
      {
        if (isset($this->traits[$tname]))
        {
          $trait = $this->traits[$tname];
          if (is_array($trait))
          {
            foreach ($trait as $tpname => $tpval)
            {
              if (!isset($data[$tpname]))
              {
                $data[$tpname] = $tpval;
              }
            }
          }
        }
      }
    }
  }

  public function compileConfig ($opts=[], $compiled=[])
  {
    foreach ($this->routes as $route)
    {
      $route->compileConfig($opts, $compiled);
    }
    return $compiled;
  }

}

class RimlRoute
{
  use RimlCommon;

  /**
   * The name of the route (used by the Router plugin.)
   */
  public $name;
  /**
   * The path we are testing against. 
   * Use {placeholder} style for placeholders.
   */
  public $path;
  /**
   * The HTTP Methods used by this route.
   * Set to false to use the parent route path.
   */
  public $http;
  /**
   * If this is true, then this route will not be added to the Router
   * list, but will be used to generate sub-routes using a set of default
   * options.
   */
  public $virtual = false;
  /**
   * The return value should match this schema.
   */
  public $responseSchema;
  /**
   * If we are a PUT, POST, or PATCH, the body should match this schema.
   */
  public $requestSchema;
  /**
   * Query string parameters. (TODO: to be implemented.)
   */
  public $queryParams;
  /**
   * Custom headers (TODO: to be implemented.)
   */
  public $headers;
  /**
   * Test definitions (TODO: to be implemented.)
   */
  public $tests;
  /**
   * This is the default route.
   */
  public $defaultRoute = false;

  public function __construct ($rname, $rdef, $parent)
  {
    if (!is_array($rdef))
    {
      $rdef = [];
    }
    $this->parent = $parent;
    $this->root   = $parent->root;
    foreach ([RIML_COMMON_PROPS, RIML_ROUTE_PROPS] as $psrc)
    {
      foreach ($psrc as $pname)
      {
        if (isset($rdef[$pname]))
        {
          $this->$pname = $rdef[$pname];
          unset($rdef[$pname]);
        }
      }
    }
    if (isset($rdef['.controller']) && $rdef['.controller'] && !isset($this->controller))
    {
      $this->controller = $rname;
    }
    elseif (isset($rdef['.method']) && $rdef['.method'] && !isset($this->method))
    {
      $mname = str_replace('/', '', $rname);
      $this->method = $this->root->method_prefix.$mname;
    }
    if (!isset($this->path) && !$this->virtual)
    {
      $this->path = $rname;
    }
    foreach (RIML_HTTP_PROPS as $hname)
    {
      if (isset($rdef[$hname]))
      {
        if (!is_array($rdef[$hname]))
          $rdef[$hname] = [];
        $rdef[$hname]['http'] = $hname;
        if (!isset($hdef[$hname]['path']))
          $rdef[$hname]['path'] = false; // Force parent path use.
      }
    }
    $this->addRoutes($rdef);
  }

  public function compileConfig ($opts, &$compiled, $rdef=[])
  {
    $isDefault = $this->defaultRoute;
    $addIt = $this->defaultRoute ? false : true;
    foreach (RIML_ROUTER_PROPS as $sname => $tname)
    {
      if ($sname == 'path')
      { // Special handling for path.
        if (isset($this->path))
        {
          $path = $this->path;
          if (strpos($path, '/') === false)
          {
            $path = "/$path/";
          }
          if (isset($rdef['path']))
          {
            $rdef['path'] .= $path;
            $rdef['path'] = str_replace('//', '/', $rdef['path']);
          }
          else
          {
            $rdef['path'] = $path;
          }
        }
      }
      elseif (isset($this->$sname))
      {
        $rdef[$tname] = $this->$sname;
      }
    }
    if (!isset($rdef['name']))
    {
      if (isset($rdef['controller']))
        $name = $rdef['controller'];
      else
        $name = '';
      if (isset($rdef['action']))
      {
        $name .= str_replace($this->root->method_prefix, '_', $rdef['action']);
      }
      if (!isset($this->root->auto_route_names[$name]))
      {
        $rdef['name'] = $name;
        $this->root->auto_route_names[$name] = true;
      }
    }    
    if (!$this->virtual)
    {
      $compiled[] = [$rdef, $isDefault, $addIt];
    }
    unset($rdef['name']);
    foreach ($this->routes as $route)
    {
      $route->compileConfig($opts, $compiled, $rdef);
    }
  }

}
