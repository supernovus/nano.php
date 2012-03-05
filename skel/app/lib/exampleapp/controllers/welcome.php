<?php

namespace ExampleAll\Controllers;

class Welcome extends \ExampleApp\Controller
{
  public function handle_dispatch ($opts, $path)
  {
    // TODO: make me more useful.
    $this->data['title'] = "Welcome";
    return $this->display();
  }
}
