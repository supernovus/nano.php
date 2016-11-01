<?php

namespace Nano\Loader;

/** 
 * A Loader Type Trait for returning parsed PHP content.
 */
trait Content
{
  public function load ($class, $data=Null)
  {
    $file = $this->find_file($class);
    if (isset($file))
    {
      $output = \Nano\get_php_content($file, $data);
      return $output;
    }
    else
      throw new \Nano\Exception("Attempt to load invalid PHP file: '$file' (from: '$class')");
  }
}

