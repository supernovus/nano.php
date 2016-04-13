<?php

// This class represents a controller.

namespace ExampleApp;

abstract class Controller extends \Nano3\Controllers\Advanced
{
  protected $save_uri  = True;  // Set to False for login/logout pages.
  protected $need_user = False; // Set to True for user-only pages.
  // Add any extra rules you need for your controllers.

  public function __construct_example_controller ($opts=array()) 
  {
    // Register a view helper object.
    $this->data['html'] = new \Nano3\Utils\HTML(array('output'=>'echo'));
  }
}

