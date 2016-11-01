<?php

namespace Nano\Plugins;

class Controllers extends Instance 
{
  /**
   * Initialize the "screens" and "layouts" loaders,
   * using our recommended default folders.
   *
   * If you don't want any folders set, pass False.
   */
  public function use_screens ($defaults=True)
  {
    $nano = \Nano\get_instance();
    $nano->layouts = 'views';
    $nano->screens = 'views';
    if ($defaults)
    {
      $viewroot = $nano['viewroot'];
      if (!isset($viewroot))
        $viewroot = 'views';
      $nano->layouts->addDir("$viewroot/layouts");
      $nano->screens->addDir("$viewroot/screens");
    }
  }
}

