<?php

namespace Nano\Models\Mongo;

/**
 * A basic access log.
 *
 * Records a bunch of data to a database for auditing purposes.
 */
abstract class AccessLog extends \Nano\DB\Mongo\Model
{
  use \Nano\Models\Common\AccessLog;

  protected $childclass  = '\Nano\Models\Mongo\AccessRecord';
  protected $resultclass = '\Nano\DB\Mongo\Results';

  public $known_fields =
  [
    'success' => false, 'message' => null, 'context' => null, 
    'headers' => null, 'userdata' => null, 'timestamp' => 0,
  ];
}

class AccessRecord 
extends \Nano\DB\Mongo\Item 
implements \Nano\Models\Common\AccessRecord 
{}

