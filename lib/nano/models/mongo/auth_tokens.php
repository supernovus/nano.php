<?php

namespace Nano\Models\Mongo;
#use \MongoDB\BSON\ObjectID;

abstract class Auth_Tokens extends \Nano\DB\Mongo\Model
{
  use \Nano\Models\Common\Auth_Tokens;

  protected $childclass  = '\Nano\Models\Mongo\Auth_Token';
  protected $resultclass = '\Nano\DB\Mongo\Results';

  protected $token_cache = [];

  public function getToken ($id)
  {
    if (isset($this->token_cache[$id]))
    {
      return $this->token_cache[$id];
    }
    return $this->token_cache[$id] = $this->getDocById($id);
  }

  public function getUserToken ($uid)
  {
    $cname = "user_$uid";
    if (isset($this->token_cache[$cname]))
    {
      return $this->token_cache[$cname];
    }
    $ucol = $this->user_field;
    return $this->token_cache[$cname] = $this->findOne([$ucol=>$uid]);    
  }

}
