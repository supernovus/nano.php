<?php

/**
 * Build websites quickly using site-wide templates and having
 * full access to Nano4 features from each site page.
 *
 */

namespace Nano4\Plugins;
use Nano4\Exception;

/**
 * Site class.
 *
 * Load in a standalone PHP page that you want to wrap in a template
 * and provide Nano4 features to. The template can be a path to a
 * PHP file, or can be a loader:file name, such as "views:template"
 * where "views" is the loader and "template" is the view name.
 *
 */

if (!defined('SITE_DEFAULT_CONF'))
{
  define('SITE_DEFAULT_CONF', 'conf/site.json');
}

class Site
{
  protected $template; // Either a view, or a filename.
  public function start ($configfile=Null)
  {
    $nano = \Nano4\get_instance();
    if (isset($configfile))
    { // Load our provided config file.
      $nano->conf->loadFile($configfile);
    }
    elseif (file_exists(SITE_DEFAULT_CONF))
    {
      $nano->conf->loadFile(SITE_DEFAULT_CONF);
    }
    else
    {
      throw new Exception('Could not find configuration.');
    }
    if (!isset($nano->conf->template))
    {
      throw new Exception('No template defined in configuration.');
    }
    $this->template = $nano->conf->template;
    // Okay, we have our template, now let's start capturing the page content.
    $nano->capture->start();
  }
  public function end ()
  {
    $nano = \Nano4\get_instance();
    $content = $nano->capture->end();
    $template = $this->template;
    $loader = Null;
    if (strpos($template, ':') !== False)
    {
      $tparts = explode(':', $template);
      if (isset($nano->lib[$tparts[0]]))
      {
        $loader   = $tparts[0];
        $template = $tparts[1];
      }
    }
    $pagedata = array(
      'content' => $content, // The page content to insert.
      'nano'    => $nano,    // Provide Nano to the template.
    );
    if (isset($loader))
    { // We're using a loader.
      $output = $nano->lib[$loader]->load($template, $pagedata);
    }
    else
    { // We're using an include file.
      $output = \Nano4\get_php_content($template, $pagedata);
    }
    echo $output;
  }

}
