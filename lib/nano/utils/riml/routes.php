<?php

namespace Nano\Utils\RIML;

/**
 * A map of RIML property names to Nano Router parameter names.
 */
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

/**
 * Force an array in the case of scalar values.
 */
const RIML_ROUTER_ARRAY = ['methods'];

/**
 * Take a RIML object, and generate a Nano Route configuration from it.
 */
class Routes
{
  public $auto_route_names = []; // Automatically generated route names.

  public function compile ($riml, $compiled=[])
  {
    if ($riml->hasRoutes())
    {
      foreach ($riml->getRoutes() as $route)
      {
        $this->compileRoute($route, $compiled);
      }
    }
    return $compiled;
  }

  protected function compileRoute ($route, &$compiled=[], $rdef=[])
  {
    $isDefault = $route->defaultRoute;
    $addIt = $route->defaultRoute ? false : true;
    foreach (RIML_ROUTER_PROPS as $sname => $tname)
    {
      if ($sname == 'path')
      { // Special handling for path.
        if (isset($route->path))
        {
          $path = $route->path;
          if ($path === false)
          { // Directly using the parent path.
            continue;
          }
          if (strpos($path, '/') === false)
          {
            $path = "/$path/";
          }
          if (isset($rdef[$tname]))
          {
//            error_log("appending $path to {$rdef[$tname]}");
            $rdef[$tname] .= $path;
            $rdef[$tname] = str_replace('//', '/', $rdef[$tname]);
          }
          else
          {
//            error_log("setting $sname/$tname to $path");
            $rdef[$tname] = $path;
          }
        }
      }
      elseif (isset($route->$sname))
      {
        if (in_array($tname, RIML_ROUTER_ARRAY) && !is_array($route->$sname))
          $rdef[$tname] = [$route->$sname];
        else
          $rdef[$tname] = $route->$sname;
      }
    }
    if (!$route->virtual && !isset($rdef['name']))
    { // Auto-naming feature.
      if (isset($rdef['controller']))
        $name = $rdef['controller'];
      else
        $name = '';
      if (isset($rdef['action']))
      {
        $aname = str_replace($route->root->method_prefix, '_', $rdef['action']);
        if ($aname != "_default")
          $name .= $aname;
      }
      if (!isset($route->root->auto_route_names[$name]))
      {
        $rdef['name'] = $name;
        $this->auto_route_names[$name] = true;
      }
    }    
    if (!$route->virtual)
    {
      $compiled[] = [$rdef, $isDefault, $addIt];
    }
    unset($rdef['name']);
    if ($route->hasRoutes())
    {
      foreach ($route->getRoutes() as $subroute)
      {
        $this->compileRoute($subroute, $compiled, $rdef);
      }
    }
  }

}
