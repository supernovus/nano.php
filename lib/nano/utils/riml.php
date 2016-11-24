<?php

namespace Nano\Utils;

/**
 * Routing Information Modeling Language
 *
 * A YAML-based format for describing routing information.
 * Can be used for several purposes. This libary is an implementation of the
 * core specification. Look in the Nano\Utils\RAML\ namespace for libraries
 * that perform actions based on an initialized RIML object.
 *
 * In addition to this PHP implementation, I am writing a Node.js one too.
 *
 * It's loosely inspired by RAML.
 */

/**
 * The RIML version.
 */
const RIML_VERSION = '1.0-DRAFT-4';

/**
 * The namespace RIML child classes are defined in.
 */
const RIML_NS = "\\Nano\\Utils\\";

/**
 * Properties allowed in root document, and Route documents.
 */
const RIML_COMMON_PROPS = 
[
  'title', 'description', 'controller', 'method', 'apiType', 'authType',
];

/**
 * Properties allowed in Route documents.
 */
const RIML_ROUTE_PROPS =
[
  'name', 'path', 'http', 'responseSchema', 'requestSchema',
  'pathParams', 'queryParams', 'headers', 'tests', 'examples',
  'defaultRoute', 'redirect', 'redirectRoute',
  'virtual', 'noPath',
];

/**
 * Route Properties that are a map of objects.
 */
const RIML_ROUTE_OBJECT_MAP =
[
  'pathParams'  => 'RimlParam',
  'queryParams' => 'RimlParam',
  'headers'     => 'RimlParam',
];

/**
 * Route Properties that are an array of objects.
 */
const RIML_ROUTE_OBJECT_ARRAY =
[
  'tests'    => 'RimlTest',
  'examples' => 'RimlExample',
];

/**
 * Allowed HTTP methods as virtual properties.
 */
const RIML_HTTP_PROPS =
[
  'GET', 'PUT', 'POST', 'DELETE', 'PATCH', 'HEAD'
];

/**
 * Properties allowed in Parameters (queryParams, pathParams, headers.)
 */
const RIML_PARAM_PROPS =
[
  'title', 'description', 'type', 'required', 'multiple',
];

/**
 * Properties allowed in RimlCall documents (RimlTest, RimlExample).
 */
const RIML_CALL_PROPS =
[
  'title', 'description', 'request', 'pathParams', 'queryParams', 'headers'
];

/**
 * Properties allowed in RimlTest documents.
 */
const RIML_TEST_PROPS =
[
  'validateRequest', 'validateResponse', 'expectedResponse', 'responseClass',
];

/**
 * Properties allowed in RimlExample documents.
 */
const RIML_EXAMPLE_PROPS =
[
  'response',
];

/**
 * A trait for properties common to all RIML classes.
 */
trait RimlBase
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
}

/**
 * A trait for properties common to the root RIML class, and RimlRoute class.
 */
trait RimlRouteInfo
{
  use RimlBase;

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
  use RimlRouteInfo;

  public $method_prefix = 'handle_';
  public $confdir;

  protected $templates = []; // Templates for later use.

  protected $traits = []; // Traits for later use.

  protected $included = []; // A list of files we've included.

  protected $sources = []; // Source files marked as .includePoly: true

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
        return $self->includeFile($value, true);
      },
      '!includePath' => function ($value, $tag, $flags) use ($self)
      {
        return $self->includeFile($value, false);
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

  protected function includeFile ($value, $setNoPath)
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
      if ($setNoPath && !isset($yaml['noPath']))
        $yaml['noPath'] = true;
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

  public function version ()
  {
    return RIML_VERSION;
  }

}

class RimlRoute
{
  use RimlRouteInfo;

  /**
   * The internal identifier.
   */
  public $route_name;
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
   * If this is true, then we don't automatically derive a path name from
   * the route identifier.
   */
  public $noPath = false;
  /**
   * The return value should match this schema.
   */
  public $responseSchema;
  /**
   * If we are a PUT, POST, or PATCH, the body should match this schema.
   */
  public $requestSchema;
  /**
   * Path parameters.
   */
  public $pathParams;
  /**
   * Query string parameters.
   */
  public $queryParams;
  /**
   * Custom headers.
   */
  public $headers;
  /**
   * Test definitions.
   */
  public $tests;
  /**
   * Documentation examples.
   */
  public $examples;
  /**
   * This is the default route.
   */
  public $defaultRoute = false;
  /**
   * This route redirects here.
   */
  public $redirect;
  /**
   * The redirect is a Nano Route name.
   */
  public $redirectRoute = false;

  public function __construct ($rname, $rdef, $parent)
  {
    if (!is_array($rdef))
    {
      $rdef = [];
    }

    $this->parent     = $parent;
    $this->root       = $parent->root;
    $this->route_name = $rname;

    foreach ([RIML_COMMON_PROPS, RIML_ROUTE_PROPS] as $psrc)
    {
      foreach ($psrc as $pname)
      {
        if (isset($rdef[$pname]))
        {
          if (isset(RIML_ROUTE_OBJECT_MAP[$pname]))
          {
            $classname = RIML_NS . RIML_ROUTE_OBJECT_MAP[$pname];
            $this->$pname = [];
            foreach ($rdef[$pname] as $mapkey => $mapval)
            {
              $this->$pname[$mapkey] = new $classname($mapval, $this);
            }
          }
          elseif (isset(RIML_ROUTE_OBJECT_ARRAY[$pname]))
          {
            $classname = RIML_NS . RIML_ROUTE_OBJECT_ARRAY[$pname];
            $this->$pname = [];
            foreach ($rdef[$pname] as $arrayval)
            {
              $this->$pname[] = new $classname($arrayval, $this);
            }
          }
          else
          {
            $this->$pname = $rdef[$pname];
          }
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
    if (!isset($this->path) && !$this->noPath)
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

}

/**
 * A shared class for Query Parameters and HTTP Headers.
 */
class RimlParam
{
  use RimlBase;

  public $type;
  public $required = false;
  public $multiple = false;

  public function __construct ($data, $parent)
  {
    $this->parent = $parent;
    $this->root   = $parent->root;
    foreach (RIML_PARAM_PROPS as $pname)
    {
      if (isset($data[$pname]))
      {
        $this->$pname = $data[$pname];
      }
    }
  }
}

/**
 * The base class for Tests and Examples.
 */
abstract class RimlCall
{
  use RimlBase;

  public $request;
  public $queryParams;
  public $pathParams;
  public $headers;

  public function __construct ($data, $parent)
  {
    $this->parent = $parent;
    $this->root   = $parent->root;
    $props = array_merge(RIML_CALL_PROPS, self::call_props);
    foreach ($props as $pname)
    {
      if (isset($data[$pname]))
      {
        $this->$pname = $data[$pname];
      }
    }
  }
}

/**
 * A Test for a Route.
 */
class RimlTest extends RimlCall
{
  const call_props = RIML_TEST_PROPS;
  public $validateRequest  = false;
  public $validateResposne = false;
  public $expectedResponse;
  public $responseClass;
}

/**
 * An example of a Route.
 */
class RimlExample extends RimlCall
{
  const call_props = RIML_EXAMPLE_PROPS;
  public $response;
}

