<?php

namespace Controllers;

class Invalid extends \ExampleApp\Controller
{
  public function handle_dispatch ($opts, $path)
  {
    $this->data['title'] = "Four Oh Four";
    return $this->display();
  }
}

