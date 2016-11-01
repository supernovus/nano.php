<?php

namespace TestApp\Controllers;

class Example extends \Nano\Controllers\Basic
{
  public function handle_default ($context)
  {
    $name = $this->name();
    $output = "Hello from $name";
    if (isset($context['name']))
      $output .= ", how are you {$context['name']}?";
    else
      $output .= '.';
    return $output;
  }
}

