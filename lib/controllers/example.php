<?php

namespace Controllers;

class Example extends \Nano4\Controllers\Basic
{
  public function handle_dispatch ()
  {
    $name = $this->name();
    return "Hello from $name";
  }
}

