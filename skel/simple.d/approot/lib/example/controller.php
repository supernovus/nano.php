<?php

namespace Example;

/**
 * The base controller class that all other controllers will be derived
 * from. Put anything that is common to your controllers in here.
 */
abstract class Controller extends \Nano4\Controllers\Advanced
{
  protected $save_uri  = True; // Set to False for login/logout pages.
  protected $need_user = True; // Set to False on pages that don't need users.
  // Add any extra rules you need for your controllers.

  /**
   * Any methods in called __construct_{name}_controller where name is
   * a valid identifier string, will be called by the global constructor,
   * and passed any options sent to it.
   */
  public function __construct_example_controller ($opts=[]) 
  {
    // Register a view helper object.
    $this->data['html'] = new \Nano4\Utils\HTML();
  }
}

