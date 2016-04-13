<?php

namespace Example\Controllers;

/**
 * This controller will be shown if no route is matched.
 */
class Invalid extends \Example\Controller
{
  protected $need_user = false;

  public function handle_default ($req)
  {
    $this->data['title'] = "Four Oh Four";
    return $this->display();
  }
}

