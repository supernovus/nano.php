<?php

namespace Nano4\Models\Mongo;

/**
 * A basic access log.
 *
 * Records a bunch of data to a database for auditing purposes.
 */
class AccessLog extends \Nano4\DB\Mongo\Model
{
  use \Nano4\Models\Common\AccessLog;

  protected $childclass  = '\Nano4\Models\Mongo\AccessRecord';
  protected $resultclass = '\Nano4\DB\Mongo\Results';

  public $known_fields =
  [
    'success' => false, 'message' => null, 'context' => null, 
    'headers' => null, 'userdata' => null, 'timestamp' => 0,
  ];
}

class AccessRecord 
extends \Nano4\DB\Mongo\Item 
implements \Nano4\Models\Common\AccessRecord 
{}

