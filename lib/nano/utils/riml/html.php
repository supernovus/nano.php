<?php

namespace Nano\Utils\RIML;

use const \Nano\Utils\RIML_COMMON_PROPS;
use const \Nano\Utils\RIML_ROUTE_PROPS;
use const \Nano\Utils\RIML_HTTP_PROPS;
use const \Nano\Utils\RIML_CALL_PROPS;
use const \Nano\Utils\RIML_EXAMPLE_PROPS;

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
  protected $output_dir;
  protected $index_filename = 'index.html';
  protected $index_template = 'index';
  protected $group_template = 'group';
  protected $use_groups     = true;

  protected $route_index; // The main RIML file for this document set.
  protected $route_info;  // If using groups, a virtual route for each group.
  protected $route_data;  // The route data for each route for rendering.

  protected $schemata = []; // inline schemata and/or URI references.

  public function __construct ($opts=[])
  {
    foreach (get_object_vars($this) as $field => $default)
    {
      if (isset($opts[$field]))
        $this->$field = $opts[$field];
    }
  }

  public function compile ($riml)
  {
    $this->route_index = $riml;
    $this->route_data = [];
    if ($this->use_groups)
    { // There will be separate route info for each group.
      $this->route_info = [];
    }
    $routes = $riml->getRoutes();
    foreach ($routes as $route)
    {
      if ($this->use_groups)
      { // The top level route names are the group names.
        $group = $route->route_name;
        $this->route_info[$group] = $route; // the first route is the group.
        $this->route_data[$group] = [];     // any non-virtual routes in here.
      }
      else
      {
        $group = null;
      }
      $this->compileRoute($route, $group);
    }
  }

  public function write ($output_dir=null)
  {
    if (!isset($output_dir))
    {
      if (isset($this->output_dir))
      {
        $output_dir = $this->output_dir;
      }
      else
      {
        throw new \Exception("Cannot output files, no directory specified");
      }
    }

    $nano = \Nano\get_instance();
    $vl = $this->view_loader;
    $vl = $nano->$vl;
    $gt = $this->group_template;
    $it = $this->index_template;

    if (!file_exists($output_dir))
    {
      mkdir($this->output_dir, 0755, true);
    }

    if ($this->use_groups)
    {
      $index_data =
      [
        'info'   => $this->route_index,
        'groups' => [],
      ];

      foreach ($this->route_info as $groupname => $groupinfo)
      {
        $group_routes = $this->route_data[$groupname];
        $group_data =
        [
          'info'   => $groupinfo,
          'routes' => $group_routes,
        ];
        $group_content = $vl->load($gt, $group_data);
        $group_filename = $this->output_dir . "/$groupname.html";
        file_put_contents($group_filename, $group_content);
        $index_data['groups'][$groupname] = $groupinfo;
      }
      $index_content = $vl->load($it, $index_data);
      $index_filename = $output_dir . '/' . $this->index_filename;
      file_put_contents($index_filename, $index_content);
    }
    else
    {
      $index_data =
      [
        'info'   => $this->route_index,
        'routes' => $this->route_data,
      ];
      $index_content = $vl->load($it, $index_data);
      $index_filename = $output_dir . '/' . $this->index_filename;
      file_put_contents($index_filename, $index_content);
    }
  }

  protected function compileRoute ($route, $group=null, $rdef=[])
  {
    foreach (array_merge(RIML_COMMON_PROPS,RIML_ROUTE_PROPS) as $pname)
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
        if (isset($rdef['examples']))
        { // Standalone examples are the preferred documentation type.
          foreach ($rdef['examples'] as $example)
          {
            foreach (['request','response'] as $ep)
            {
              if (isset($example->$ep))
              {
                $this->addSchema($example->$ep);
              }
            }
          }
        }
        elseif (isset($rdef['tests']))
        { // Tests can be used as examples.
          foreach ($rdef['tests'] as $test)
          {
            foreach (['request','expectedResposne'] as $tp)
            {
              if (isset($test->$tp))
              {
                $this->addSchema($test->$tp);
              }
            }
          }
        }
      }
      if (!$route->virtual)
      {
        if ($this->use_groups)
        {
          $this->route_data[$group][] = $route;
        }
        else
        {
          $this->route_data[] = $route;
        }
      }
      foreach ($route->getRoutes() as $subroute)
      {
        $this->compileRoute($subroute, $group, $rdef);
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