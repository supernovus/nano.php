<?php

namespace Controllers;

class Example3 extends \Nano3\Base\Controller
{
  public function handle_dispatch ()
  {
    #$nano = \Nano3\get_instance();
    #$name = $nano->controllers->id($this);
    $name = $this->name();
    return "Hello from $name";
  }
}

