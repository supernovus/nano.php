<?php

namespace Nano\Models\Common;

trait Auth_Tokens
{
  abstract public function getToken ($id);
  abstract public function getUserToken ($uid);
  abstract public function newChild ($data=[], $opts=[]);

  public $key_field    = 'authkey';
  public $user_field   = 'user';
  public $expire_field = 'expire';

  public $hashType   = 'sha256';

  public $errors        = [];

  public $format_string = '01';

  public $default_expire = 0;

  /**
   * Get an Auth Token for the given App Token.
   */
  public function authToken ($appToken)
  {
    $bits = $this->parseToken($appToken);
    if (!isset($bits)) return; // invalid token.
    $sid = $bits[0];
    $ahash = $bits[1];
    $uhash = $this->authHash($sid, $ahash);
    $len = sprintf('%02d', strlen($sid));
    $token = $this->format_string . $len . $sid . $uhash;
    return $token;
  }

  /**
   * Get the hash portion of an Auth Token.
   */
  protected function authHash ($sid, $ahash)
  {
    return hash($this->hashType, trim($sid.$ahash));
  }

  /**
   * Get the 'sid' and 'hash' from a token.
   */
  public function parseToken ($token)
  {
    $format = substr($token, 0, 2);
    if ($format != $this->format_string)
    {
      $this->errors[] = 'invalid_format';
      return;
    }
    $len = intval(substr($token, 2, 2));
    if (!$len)
    {
      $this->errors[] = 'invalid_length';
      return;
    }
    $sid = substr($token, 4, $len);
    $offset = $len + 4;
    $hash = substr($token, $offset);
    return [$sid, $hash];
  }

  /**
   * Get a user from an Auth Token (if it's valid.)
   */
  public function getUser ($authToken)
  {
    $bits = $this->parseToken($authToken);
    $sid = $bits[0];
    $uhash = $bits[1];
    $row = $this->getToken($sid);
    if (!isset($row))
    {
      $this->errors[] = 'invalid_token_sid';
      return;
    }
    if ($row->expired())
    {
      $this->errors[] = 'expired_token';
      return;
    }
    $user = $row->getUser();
    if (!isset($user)) return; // invalid user.
    $ahash = $row->appHash($user);
    $chash = $this->authHash($sid, $ahash);
    if ($chash == $uhash)
    {
      return $user;
    }
    else
    {
      $this->errors[] = 'invalid_token_hash';
      return;
    }
  }

  /**
   * Create a new token session for a given user.
   *
   * @param $def mixed  see below
   *
   * The $def can be in a few different formats.
   */
  public function newToken ($def)
  {
    $ucol = $this->user_field;
    if (is_object($def) && is_callable([$def, 'get_id']))
    { // A user object was passed.
      $uid = $def->get_id();
      $def = [$ucol=>$uid];
    }
    elseif (is_array($def) && isset($def[$ucol]))
    {
      $uid = $def[$ucol];
      if (is_object($uid) && is_callable([$uid, 'get_id']))
      { // It's an object, convert it to a uid.
        $uid = $uid->get_id();
        $def[$ucol] = $uid;
      }
    }
    else
    {
      throw new \Exception("Invalid user sent to newToken()");
    }
    $ecol = $this->expire_field;
    if (isset($def[$ecol]))
    { 
      if (is_string($def[$ecol]))
      { // Ensure the value is in the correct format.
        $def[$ecol] = time() + $this->expire_value($def[$ecol]);
      }
      elseif (!is_numeric($def[$ecol]))
      {
        throw new \Exception("Invalid expire value sent to newToken()");
      }
    }
    else
    { // Use the default expire value.
      if (is_int($this->default_expire))
        $def[$ecol] = $this->default_expire;
      elseif (is_string($this->default_expire))
        $def[$ecol] = $this->expire_value($this->default_expire);
    }
    $kcol = $this->key_field;
    $def[$kcol] = $this->generate_key();
    $token = $this->newChild($def);
    $token->save();
    return $token;
  }

  public function generate_key ()
  {
    return hash($this->hashType, uniqid('', true));
  }

  public function expire_value ($exstr)
  {
    if (strpos($expstr, 'm') !== false)
    { // number of minutes.
      $expval = intval(preg_replace('/\s*m/', '', $expstr));
      $expval *= 60;
    }
    elseif (strpos($expstr, 'h') !== false)
    { // number of hours.
      $expval = intval(preg_replace('/\s*h/', '', $expstr));
      $expval *= 60 * 60;
    }
    elseif (strpos($expstr, 'd') !== false)
    { // number of days.
      $expval = intval(preg_replace('/\s*d/', '', $expstr));
      $expval *= 24 * 60 * 60;
    }
    elseif (strpos($expstr, 'w') !== false)
    { // number of weeks.
      $expval = intval(preg_replace('/\s*w/', '', $expstr));
      $expval *= 7 * 24 * 60 * 60;
    }
    elseif (strpos($expstr, 'M') !== false)
    { // number of months (30 days.)
      $expval = intval(preg_replace('/\s*M/', '', $expstr));
      $expval *= 30 * 24 * 60 * 60;
    }
    else
    { // unknown format, assume seconds.
      $expval = intval($expstr);
    }
    return $expval;
  }

}