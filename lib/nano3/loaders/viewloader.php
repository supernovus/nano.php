<?php

namespace Nano3\Loaders;

/* The base class for loading views.
   Extend this as needed, or just make multiple loaders using
   this class if you have multiple types of views (layouts versus screens, etc.)
 */
class ViewLoader extends \Nano3\Loader
{
  public function load ($class, $data=NULL)
  {
    $file = $this->find($class);
    if (isset($file))
    {
      $output = \Nano3\get_php_content($file, $data);
      return $output;
    }
    else
      throw new \Nano3\Exception("Attempt to load invalid view: $file");
  }
}

