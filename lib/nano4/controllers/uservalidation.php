<?php

namespace Nano4\Controllers;

/**
 * Ensure the current user is valid.
 * This requires the 'UserAuth' trait to be loaded first,
 * and that a validate_user() method is defined.
 *
 * If you don't require user validation, set a property called
 * $validate_user to False.
 */

trait UserValidation
{
  protected function __construct_uservalidation_controller ($opts=[])
  {
    if (property_exists($this, 'validate_user'))
      $validate_user = $this->validate_user;
    else
      $validate_user = True;

    if 
    (
      $validate_user
      &&
      property_exists($this, 'user') 
      && 
      isset($this->user) 
      && 
      method_exists($this, 'validate_user')
    )
    {
      $this->validate_user($this->user);
    }
  }
}
