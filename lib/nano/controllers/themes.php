<?php

namespace Nano\Controllers;

/**
 * Support themes, defined in 'conf/themes.d/'
 */

trait Themes
{
  protected $currentTheme;

  public function setTheme ($themeName, $override=true)
  {
    if (isset($this->currentTheme) && !$override)
      return; // Will not override current theme.

    $nano = \Nano\get_instance();
    $conf = $nano->conf;
    $vroot = $nano['viewroot'];
    if (!isset($vroot))
      $vroot = 'views';
    
    if (isset($conf->themes->$themeName))
    {
      $this->currentTheme = $themeName;
      $themeDef = $conf->themes->$themeName;
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
