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
  public function appToken ($user=null)
  {
    $token = $this->parent->format_string;
    $sid = $this->tokenId();
    $len = sprintf('%02d', strlen($sid));
    $token .= $len;
    $token .= $sid;
    $ahash = $this->appHash($user);
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
      error_log("invalid user");
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
    $kfield = $this->parent->key_field;
    return hash($this->parent->hashType, trim($this->$kfield, $user->$tfield));
  }

  /**
   * Reset the auth key.
   */
  public function resetKey ($autosave=true)
  {
    $kfield = $this->parent->key_field;
    $newkey = $this->parent->generate_key();
    $this[$kfield] = $newkey;
    if ($autosave)
      $this->save();
  }

  /**
   * Reset the expire value.
   */
  public function resetExpire ($value, $autosave=true)
  {
    $efield = $this->parent->expire_field;
    if (is_string($value))
    {
      $value = (time() + $this->parent->expire_value($value));
    }
    elseif (!is_numeric($value))
    {
      throw new \Exception("Invalid expire value sent to resetExpire()");
    }

    $this[$efield] = $value;
    
    if ($autosave)
      $this->save();
  }

  /**
   * Regenerate a token.
   */
  public function regenerate ($expire=null, $autosave=true)
  {
    if (!isset($expire))
      $expire = $this->parent->default_expire;
    $this->resetKey(false);
    $this->resetExpire($expire, false);
    if ($autosave)
      $this->save();
  }

  /**
   * Return if the token has expired.
   */
  public function expired ()
  {
    $ecol = $this->parent->expire_field;
    if (isset($this->$ecol) && $this->$ecol != 0)
    { // An expire value is set, let's see if it's past time.
      $ctime = time();
      $etime = $this->$ecol;
      if ($ctime > $etime)
      { // The token is expired.
        return true;
      }
    }
    return false;
  }

  /**
   * Expire the token now.
   */
  public function expireNow ($autosave=true)
  {
    $this->resetExpire(time(), $autosave);
  }

}
