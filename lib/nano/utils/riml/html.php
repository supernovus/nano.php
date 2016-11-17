<?php

namespace Nano\Utils\RIML;

use const \Nano\Utils\RIML_COMMON_PROPS;
use const \Nano\Utils\RIML_ROUTE_PROPS;
use const \Nano\Utils\RIML_HTTP_PROPS;

/**
 * Generate HTML documentation from RIML documents.
 * Currently uses Nano templates to do the processing.
 */

class HTML
{
  protected $view_loader = 'riml';
  protected $inline_schema = true;
  protected $schema_link_uri = null;
  protected $schema_base_dir = '';

  protected $route_data = [];

  protected $schemata = []; // either inline schemata, or references.

  public function __construct ($opts=[])
  {
    foreach (get_object_vars($this) as $field => $default)
    {
      if (isset($opts[$field]))
        $this->$field = $opts[$field];
    }
  }

  public function build ($riml)
  {
    $routes = $riml->getRoutes();
    foreach ($routes as $route)
    {
      $this->buildRoute($route);
    }
  }

  public function output ()
  {
    // TODO: implement me.
    return;
  }

  public function buildRoute ($route, $rdef=[])
  {
    foreach (RIML_COMMON_PROPS+RIML_ROUTE_PROPS as $pname)
    {
      if ($pname == 'path')
      { // Special handling for path.
        if (isset($route->path))
        {
          $path = $route->path;
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
      elseif (isset($route->$pname))
      { // Simply compose it into the current definition.
        $rdef[$pname] = $route->$pname;
      }
      if (isset($rdef['apiType']))
      { // Schemas are only valid if there is an API type defined.
        foreach (['responseSchema','requestSchema'] as $schemaProp)
        {
          if (isset($rdef[$schemaProp]))
          {
            $this->addSchema($rdef[$schemaProp]);
          }
        }
        if (isset($rdef['tests']))
        {
          foreach (['request','expectedResponse'] as $exampleProp)
          {
            if (isset($rdef['tests'][$exampleProp]))
            {
              $this->addSchema($rdef['tests'][$exampleProp]);
            }
          }
        }
      }
      if (!$route->virtual)
      {
        $this->route_data[] = $route;
      }
      foreach ($route->getRoutes() as $subroute)
      {
        $this->buildRoute($subroute, $rdef);
      }
    }
  }

  protected function addSchema ($schemaFile)
  {
    if (!isset($this->schemata[$schemaFile]))
    {
      $schemaSpec = [];
      if ($this->inline_schema && file_exists($schemaFile))
      {
        $schemaDir = $this->schema_base_dir;
        $schemaSpec['text'] = file_get_contents($schemaDir.$schemaFile);
      }
      if (isset($this->schema_link_uri))
      {
        $schemaDir = dirname($schemaFile);
        $schemaUri = $this->schema_link_uri[$schemaDir];
        $schemaSpec['link'] = $schemaUri . basename($schemaFile);
      }
      $this->schemata[$schemaFile] = $schemaSpec;
    }
  }

}