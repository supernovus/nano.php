<?php

namespace Nano\Models\PDO;

abstract class Auth_Tokens extends \Nano\DB\PDO\Model
{
  use \Nano\Models\Common\Auth_Tokens;

  protected $childclass  = '\Nano\Models\PDO\Auth_Token';
  protected $resultclass = '\Nano\DB\PDO\ResultSet';

  protected $token_cache = [];

  public function getToken ($id)
  {
    if (isset($this->token_cache[$id]))
    {
      return $this->token_cache[$id];
    }
    return $this->token_cache[$id] = $this->getRowById($id);
  }

  public function getUserToken ($uid)
  {
    if (is_object($uid))
      $uid = $uid->get_id();
    $cname = "user_$uid";
    if (isset($this->token_cache[$cname]))
    {
      return $this->token_cache[$cname];
    }
    $ucol = $this->user_field;
    return $this->token_cache[$cname] = $this->getRowWhere([$ucol=>$uid]);
  }

}