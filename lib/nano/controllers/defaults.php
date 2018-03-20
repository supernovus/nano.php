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

    // We don't recommend using the 'construct_defaults' style anymore.
    if (isset($nano['construct_defaults']) && $nano['construct_defaults'])
    {
      if (isset($nano['default_theme']) && is_callable([$this,'setTheme']))
      {
        $this->setTheme($nano['default_theme'], ['override'=>false]);
      }

      if (!isset($this->layout) && isset($nano['default_layout']))
      {
        $this->layout = $nano['default_layout'];
      }
    }

    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';
  }
}

