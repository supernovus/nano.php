<?php

namespace Nano\Models\Mongo;

abstract class Auth_Token extends \Nano\DB\Mongo\Item
{
  use \Nano\Models\Common\Auth_Token;

  public function tokenId ()
  {
    return (string)$this->_id;
  }
}
