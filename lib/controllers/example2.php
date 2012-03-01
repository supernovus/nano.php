<?php

class Example2_Controller
{
  public function handle_dispatch ()
  {
    $nano = get_nano_instance();
    $name = $nano->controllers->id($this);
    return "Hello from $name";
  }
}

