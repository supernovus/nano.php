<?php

namespace Nano4\Controllers;

/**
 * If not overridden, specify some defaults.
 */

trait Defaults
{
  protected function __construct_defaults_controller ($opts=[])
  {
    if (!isset($this->layout))
    {
      $nano = \Nano4\get_instance();
      $this->layout = $nano['layout.default'];
    }
    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';
  }
}

