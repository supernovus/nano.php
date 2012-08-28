<?php

namespace Controllers;

class Example3 extends \Nano3\Base\Controllers\Basic
{
  public function handle_dispatch ()
  {
    $name = $this->name();
    return "Hello from $name";
  }
}

