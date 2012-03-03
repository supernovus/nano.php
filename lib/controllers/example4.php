<?php

// A Nano3 controller using the oldstyle naming system.

class Example4_Controller
{
  public function handle_dispatch ()
  {
    $nano = \Nano3\get_instance();
    $name = $nano->controllers->id($this);
    return "Hello from $name";
  }
}

