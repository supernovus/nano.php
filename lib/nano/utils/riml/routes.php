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
        $rdef[$tname] = $route->$sname;
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
        $name .= str_replace($route->root->method_prefix, '_', $rdef['action']);
      }
      if (!isset($route->root->auto_route_names[$name]))
      {
        $rdef['name'] = $name;
        $route->root->auto_route_names[$name] = true;
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
