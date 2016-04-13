<?php

namespace Example\Models;

/**
 * An example model representing a database table.
 */
class Docs extends \Nano4\DB\Model
{
  protected $childclass  = '\Example\Models\Doc';
  protected $resultclass = '\Nano4\DB\ResultSet';
  // The rest of your extensions here.
}

/**
 * An example model child representing a database row.
 */
class Doc extends \Nano4\DB\Item
{
  // The rest of your extensions here.
}

