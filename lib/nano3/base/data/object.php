<?php

/* Data\Object -- Base class for all Nano3\Base\Data classes.
 *
 * Based on my old DataObjects class from PHP-Common, but far more
 * generalized. This version knows nothing about the data formats.
 *
 */

namespace Nano3\Base\Data;

abstract class Object
{
  protected $data   = array(); // The actual data we represent.
  protected $opts   = array(); // Options specific for each object.
  protected $parent;           // Will be set if we have a parent object.

  public function __construct ($mixed=Null, $opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (isset($opts['data_opts']) && is_array($opts['data_opts']))
    {
      $this->opts = $opts['data_opts'];
    }
    if (method_exists($this, 'data_init'))
    {
      $this->data_init();
    }
    if (isset($opts['spawn']) && $opts['spawn']) return; // No load.
    $this->load_data($mixed, array('clear'=>False, 'prep'=>True, 'post'=>True));
  }
  // TODO: finish me.
}
