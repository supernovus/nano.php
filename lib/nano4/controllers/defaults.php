<?php

namespace Nano4\Controllers;

/**
 * If not overridden, specify some defaults.
 */

trait Defaults
{
  protected function __construct_defaults_controller ($opts=[])
  {
    $nano = \Nano4\get_instance();
    if (!isset($this->default_url))
    {
      $default_page = $this->get_prop('default_page', 'default');
      $this->default_url = $this->get_uri($default_page);
    }
    if (!isset($this->layout))
    {
      $this->layout = $nano['layout.default'];
    }
    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';
  }
}

