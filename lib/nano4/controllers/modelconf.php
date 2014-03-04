<?php

namespace Nano4\Controllers;

/**
 * A trait that adds model configuration to your controllers.
 */

trait ModelConf
{
  protected $model_opts;    // Options to pass when loading models.

  protected function __construct_modelconf_controller ($opts=[])
  {
    $nano = \Nano4\get_instance();
    if (isset($nano->conf->models))
    {
      $this->model_opts = $nano->conf->models;
    }
  }

  // A wrapper for get_model_opts that uses some default values.
  protected function populate_model_opts ($model, $opts)
  { // Load any specific options.
    $opts = $this->get_model_opts($model, $opts, True);

    // Load any common options.
    $opts = $this->get_model_opts('.common', $opts);

    return $opts;
  }

  /**
   * Get model options from our $this->model_opts definitions.
   *
   * @param String $name              The model/group to look up options for.
   * @param Array  $opts              Current/overridden options.
   * @param Bool   $defaults          If True, use default options.
   *
   * If the $defaults is True, and we cannot find a set of options for the
   * specified model, then we will look for a set of options called '.default'
   * and use that instead.
   *
   * A special option called '.type' allows for nesting option defintions.
   * If set in any level, an option group with the name of the '.type' will be 
   * looked up, and any options in its definition will be added (if they don't
   * already exist.) Groups may have their own '.type' option, allowing for
   * multiple levels of nesting.
   *
   * The '.type' rule MUST NOT start with a dot. The group definition MUST
   * start with a dot. The dot will be assumed on all groups.
   *
   * So if a '.type' option is set to 'common', then a group called '.common'
   * will be inherited from.
   */
  protected function get_model_opts ($model, $opts=array(), $use_defaults=False)
  {
    $model = strtolower($model); // Force lowercase.
#    error_log("Looking for model options for '$model'");
    if (isset($this->model_opts) && is_array($this->model_opts))
    { // We have model options in the controller.
      if (isset($this->model_opts[$model]))
      { // There is model-specific options.
        $modeltype = Null;
        $modelopts = $this->model_opts[$model];
        if (is_array($modelopts))
        { 
          $opts += $modelopts;
          if (isset($modelopts['.type']))
          {
            $modeltype = $modelopts['.type'];
          }
        }
        elseif (is_string($modelopts))
        {
          $modeltype = $modelopts;
          if (!isset($opts['.type']))
          {
            $opts['.type'] = $modeltype;
          }
        }
        if (isset($modeltype))
        { // Groups start with a dot.
          $opts = $this->get_model_opts('.'.$modeltype, $opts);
          $func = 'get_'.$modeltype.'_model_opts';
          if (is_callable(array($this, $func)))
          {
#            error_log("  -- Calling $func() to get more options.");
            $addopts = $this->$func($model, $opts);
            if (isset($addopts) && is_array($addopts))
            {
#              error_log("  -- Options were found, adding to our set.");
              $opts += $addopts;
            }
          }
        }
      }
      elseif ($use_defaults)
      {
        $opts = $this->get_model_opts('.default', $opts);
      }
    }
#    error_log("Returning: ".json_encode($opts));
    return $opts;
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

