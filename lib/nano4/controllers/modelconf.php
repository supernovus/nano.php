<?php

namespace Nano4\Controllers;

/**
 * A trait that adds model configuration to your controllers.
 */

trait ModelConf
{
  protected function __construct_modelconf_controller ($opts=[])
  {
    $nano = \Nano4\get_instance();
    if (isset($nano->conf->models))
    {
      $this->model_opts = $nano->conf->models;
    }
  }

  // Get model options for models identified as 'db' models.
  protected function get_db_model_opts ($model, $opts)
  {
#    error_log("get_db_model_opts($model)");
    $nano = \Nano4\get_instance();
    if (isset($nano->conf->db))
    {
      return $nano->conf->db;
    }
  }

  /**
   * Return a list of models given a specific ".type" definition.
   *
   * TODO: Deep type searches.
   */
  public function get_models_of_type ($type, $deep=False)
  {
#    error_log("In get_models_of_type('$type')");
    $models = array();
    foreach ($this->model_opts as $name => $opts)
    {
#      error_log("  -- '$name' => ".json_encode($opts)); 
      if (substr($name, 0, 1) == '.') continue; // Skip groups.
      if 
      (
        is_string($opts)
        ||
        (is_array($opts) && isset($opts['.type']))
      )
      {
        if (is_string($opts))
          $modeltype = $opts;
        else
          $modeltype = $opts['.type'];

        if ($modeltype == $type)
        {
          $models[$name] = $opts;
        }
      }
    }
    return $models;
  }

}

