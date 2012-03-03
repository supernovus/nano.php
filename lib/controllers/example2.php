<?php

// For compatibility with both Nano2 and Nano3.
require_once 'lib/nano2/base/corecontroller.php';

class Example2_Controller extends CoreController
{
  public function handle_dispatch ()
  {
    //$nano = get_nano_instance();
    //$name = $nano->controllers->id($this);
    $name = $this->name();
    return "Hello from $name";
  }
}

