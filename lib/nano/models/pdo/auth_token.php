<?php

namespace Nano\Models\PDO;

abstract class Auth_Token extends \Nano\DB\PDO\Item
{
  use \Nano\Models\Common\Auth_Token;

  public function tokenId ()
  {
    return $this->id;
  }
}
