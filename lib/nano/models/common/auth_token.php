<?php

namespace Nano\Models\Common;

trait Auth_Token
{
  protected $user_model = 'users';
  protected $user_token = 'token';

  abstract public function tokenId ();

  /**
   * Return an App Token.
   */
  public function appToken ()
  {
    $token = $this->parent->format_string;
    $sid = $this->tokenId();
    $len = sprintf('%02d', strlen($sid));
    $token .= $len;
    $token .= $sid;
    $ahash = $this->appHash();
    if (!isset($ahash)) return; // no hash was returned, leave now.
    $token .= $ahash;
    return $token;
  }

  /**
   * Get a user.
   */
  public function getUser ()
  {          //row  model   ctrl
    $users = $this->parent->parent->model($this->user_model);
    $field = $this->parent->user_field;
    $user  = $users->getUser($this->$field);
    if (!$user)
    {
      $this->parent->errors[] = 'invalid_user';
      return;
    }
    return $user;    
  }

  /**
   * Get the hash portion of the App Token.
   */
  public function appHash ($user=null)
  {
    if (!isset($user))
      $user = $this->getUser();
    if (!isset($user)) return; // invalid user.
    $tfield = $this->user_token;
    $sid = $this->tokenId();
    return hash($this->hashType, trim($sid, $user->$tfield));
  }

}
