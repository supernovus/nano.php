<?php

namespace Nano\Controllers;

/**
 * If not overridden, specify some defaults.
 */

trait Defaults
{
  protected function __construct_defaults_controller ($opts=[])
  {
    $nano = \Nano\get_instance();

    if (isset($nano['default_theme']) && is_callable([$this,'setTheme']))
    {
      $this->setTheme($nano['default_theme'], false);
    }

    if (!isset($this->layout))
    {
      if (isset($nano['default_layout']))
        $this->layout = $nano['default_layout'];
      elseif (isset($nano['layout.default']))
        $this->layout = $nano['layout.default'];
    }

    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';
  }
}

