<?php

namespace Example\Controllers;

/**
 * Auth Controller: Handles login and logout pages. 
 */
class Auth extends \Example\Controller
{ // Use the Nano4 authentication controller trait.
  use \Nano4\Controllers\Auth;

  // Set up our basic settings.
  protected $need_user    = False;
  protected $save_uri     = False;
  protected $default_page = 'welcome';
}

