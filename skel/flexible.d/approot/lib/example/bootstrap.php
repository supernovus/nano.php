<?php

namespace Example;

class Bootstrap
{
  /**
   * Initialize the Nano object instance.
   *
   * @param array $nano_opts  Associative array of options.
   *                          'classroot' => '/path/to/libraries'
   *                          'viewroot'  => '/path/to/views'
   *                          'confroot'  => '/path/to/configuration'
   */
  public static function stage1 ($nano_opts)
  {
    // Load the Nano core library.
    require_once $nano_opts['classroot'] . '/nano4/init.php';

    // Load any extra configuration items.
    \Nano4\load_opts_from($nano_opts['confroot'].'/app.json',  $nano_opts);
    \Nano4\load_opts_from($nano_opts['confroot'].'/site.json', $nano_opts);

    // Bootstrap the Nano core.
    $nano = \Nano4\initialize($nano_opts);
    return $nano;
  }

  /**
   * Take the Nano object created in stage1, and set it up.
   */
  public static function stage2 ($nano)
  {
    // Activate Composer autoloader if it exists in '$classroot/vendor'.
    $nano->pragmas->composer;

    // Set up our configuration plugin.
    $nano->conf->setDir($nano['confroot']);

    // Set up the namespaces.
    $nano->controllers->addNS("\\Example\\Controllers");
    $nano->models->addNS("\\Example\\Models");

    // Use default view loaders ('screens' and 'layouts')
    // By passing 'true', we use the default 'views/screens', 'view/layouts'
    // folders.
    $nano->controllers->use_screens(true);

    // Add a view loader for components.
    $nano->components = 'views';
    $nano->components->addDir($nano['viewroot'].'/components');

     // We're using the Nano router, and want it to figure out the URL prefix.
    $nano->router = ['auto_prefix'=>true];

    // If the appinfo is enabled, let's add it explicitly.
    // Enable this in your conf/site.json file.
    if (isset($nano['appinfo']) && $nano['appinfo'])
    {
      $nano->router->add('appinfo')
        ->add('/route/:rname', 'handle_route');
    }

    // Load the common routing tables.
    $route = $nano->conf->routes['common'];
    $nano->router->load($route);

    // Get our enabled modules.
    $modules = $nano->conf->modules;
    foreach ($modules['enabled'] as $module)
    {
      if (isset($nano->conf->routes[$module]))
      {
        $route = $nano->conf->routes[$module];
        $nano->router->load($route);
      }
    }

    // Add the default handler that catches anything that doesn't match.
    $nano->router->add(['controller'=>'error'], true);

    return $nano->router;
  }

  /**
   * A convenience wrapper for both, for when no customizations are
   * required, and you just want to get the router to start the app.
   * This is what should be called in the default index.php file.
   */
  public static function all ($nano_opts)
  {
    $nano   = self::stage1($nano_opts);
    $router = self::stage2($nano);
    return $router;
  }

}
