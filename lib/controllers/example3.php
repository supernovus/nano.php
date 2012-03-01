<?php

namespace Controllers;

class Example3
{
  public function handle_dispatch ()
  {
    $nano = \Nano3\get_instance();
    $name = $nano->controllers->id($this);
    return "Hello from $name";
  }
}

