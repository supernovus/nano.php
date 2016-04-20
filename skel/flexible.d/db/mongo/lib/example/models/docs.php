<?php

namespace Example\Models;

/**
 * An example model representing a database table.
 */
class Docs extends \Nano4\DB\Mongo\Model
{
  protected $childclass  = '\Example\Models\Doc';
  protected $resultclass = '\Nano4\DB\Mongo\Results';
  // The rest of your extensions here.
}

/**
 * An example model child representing a database row.
 */
class Doc extends \Nano4\DB\Mongo\Item
{
  // The rest of your extensions here.
}

