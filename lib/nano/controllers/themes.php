<?php

namespace Nano\Controllers;

/**
 * Support themes, defined in 'conf/themes.d/'
 */

trait Themes
{
  protected $currentTheme;

  public function getTheme ($opts=[])
  {
    if (!isset($this->currentTheme)) return null;

    $justname = isset($opts['name']) ? $opts['name'] : false;
    if (isset($opts['array']) && $opts['array'])
      $asobj = false;
    elseif (isset($opts['object']))
      $asobj = $opts['object'];
    else
      $asobj = true;

    if ($justname)
      return $this->currentTheme['name'];

    if ($asobj)
      return (object)$this->currentTheme;
    else
      return $this->currentTheme;
  }

  public function setTheme ($themeName, $opts=[])
  {
    $override = isset($opts['override']) ? $opts['override'] : false;

    if (isset($this->currentTheme) && !$override)
      return; // Will not override current theme.

    $nano = \Nano\get_instance();
    $conf = $nano->conf;
    $vroot = $nano['viewroot'];
    if (!isset($vroot))
      $vroot = 'views';
    
    if (isset($conf->themes->$themeName))
    {
      $themeDef = $conf->themes->$themeName;
      $themeDef['name'] = $themeName;
      $this->currentTheme = $themeDef;

      if (isset($themeDef['layout']))
      {
        $this->layout = $themeDef['layout'];
      }

      if (isset($themeDef['views']))
      {
        foreach ($themeDef['views'] as $viewName => $viewDef)
        {
          if (!isset($nano->$viewName))
          {
            $nano->$viewName = 'views';
          }
          if (isset($viewDef['override']))
          { // Clear out existing directories and refresh.
            $nano->$viewName->dirs = [];
            $dirs = $this->get_theme_dirs($viewDef['override'], $vroot);
            $nano->$viewName->addDir($dirs);
          }
          if (isset($viewDef['insert']))
          {
            $dirs = $this->get_theme_dirs($viewDef['insert'], $vroot);
            $nano->$viewName->addDir($dirs, true);
          }
          if (isset($viewDef['append']))
          {
            $dirs = $this->get_theme_dirs($viewDef['append'], $vroot);
            $nano->$viewName->addDir($dirs);
          }
        }
      }

      if (isset($this->resources) && is_callable([$this, 'add_resource_paths']))
      { // The 'resources' trait has been loaded, let's check for things to set up.
        $localopts = isset($opts['localopts']) ? $opts['localopts'] : [];

        if (isset($themeDef['add_css_path']))
        { // Add CSS paths from theme.
          $this->add_resource_paths('css', $themeDef['add_css_path']);
        }
        if (isset($localopts[$themeName], $localopts[$themeName]['add_css_path']))
        { // Add CSS paths from localopts specific to the theme.
          $this->add_resource_paths('css', $localopts[$themeName]['add_css_path']);
        }

        if (isset($themeDef['add_js_path']))
        { // Add JS paths from theme.
          $this->add_resource_paths('js', $themeDef['add_js_path']);
        }
        if (isset($localopts[$themeName], $localopts[$themeName]['add_js_path']))
        { // Add JS paths from localopts specific to the theme. 
          $this->add_resource_paths('js', $localopts[$themeName]['add_js_path']);
        }

        if (isset($themeDef['add_css']))
        { // Add CSS from theme.
          $this->add_css($themeDef['add_css']);
        }
        if (isset($localopts[$themeName], $localopts[$themeName]['add_css']))
        { // Add CSS from localopts specific to the theme.
          $this->add_css($localopts[$themeName]['add_css']);
        }
        if (isset($localopts['add_css']))
        { // Add CSS from generic localopts.
          $this->add_css($localopts['add_css']);
        }

        if (isset($themeDef['add_js']))
        { // Add JS from theme.
          $this->add_js($themeDef['add_js']);
        }
        if (isset($localopts[$themeName], $localopts[$themeName]['add_js']))
        { // Add JS from localopts specific to the theme.
          $this->add_js($localopts[$themeName]['add_js']);
        }
        if (isset($localopts['add_js']))
        { // Add JS from generic localopts.
          $this->add_js($localopts['add_js']);
        }

        if (isset($theme['set_vars']))
        { // Set data variables from theme.
          foreach ($theme['set_vars'] as $varname => $varval)
          {
            $this->data[$varname] = $varval;
          }
        }

        if (isset($localopts['set_vars']))
        { // Set data variables from localopts.
          foreach ($localopts['set_vars'] as $varname => $varval)
          {
            $this->data[$varname] = $varval;
          }
        }

      }

      if (isset($opts['return']))
      {
        if (is_bool($opts['return']) && $opts['return'])
          return $this->getTheme($opts);
        elseif (is_array($opts['return']))
          return $this->getTheme($opts['return']);
      }
    }
  }

  private function get_theme_dirs ($dirspec, $vroot)
  {
    if (is_string($dirspec))
    {
      return $vroot . '/' . $dirspec;
    }
    elseif (is_array($dirspec))
    {
      $newdirs = [];
      foreach ($dirspec as $dir)
      {
        $newdirs[] = $vroot . '/' . $dir;
      }
      return $newdirs;
    }
    else
    {
      throw new \Exception("Invalid view dir specification.");
    }
  }
}
