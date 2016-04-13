<?php

namespace Example\Controllers;

class Welcome extends \Example\Controller
{
  /**
   * The $req is a RouteContext object.
   * It can be used to get any parameters sent.
   * See \Nano4\Plugins\Router for details.
   */
  public function handle_default ($req)
  { // TODO: make me more useful.
    $this['title'] = "Welcome";
    $this['name']  = isset($req['name']) ? $req['name'] : 'World';
    return $this->display();
  }
}
