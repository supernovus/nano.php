<?php

namespace Nano4\Loader;

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
      $output = \Nano4\get_php_content($file, $data);
      return $output;
    }
    else
      throw new \Nano4\Exception("Attempt to load invalid PHP file: '$file' (from: '$class')");
  }
}

