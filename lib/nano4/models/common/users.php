<?php

namespace Nano4\Models\Common;

trait Users
{
  abstract public function getUser ($identifier, $fieldname=null);
  abstract public function newChild ($data=[], $opts=[]);

  protected $login_field = 'email'; // The unique stringy DB field.
  protected $token_field = 'token'; // The user token field.
  protected $hash_field  = 'hash';  // The authentication hash field.
  protected $reset_field = 'reset'; // The reset code field.

  protected $hashType    = 'sha256';   // The default hash algorithm.

  protected $auth_class = "\\Nano4\\Utils\\SimpleAuth";

  public function hash_type ()
  {
    return $this->hashType;
  }

  public function auth_class ()
  {
    return $this->auth_class;
  }

  public function login_field ()
  {
    return $this->login_field;
  }

  public function token_field ()
  {
    return $this->token_field;
  }

  public function hash_field ()
  {
    return $this->hash_field;
  }

  public function reset_field ()
  {
    return $this->reset_field;
  }

  public function get_auth ($instance=false, $store=false)
  {
    $hash = $this->hashType;
    $class = $this->auth_class;
    $opts = ['hash'=>$hash, 'store'=>$store];
    if ($instance)
      $auth = $class::getInstance($opts);
    else
      $auth = new $class($opts);
    return $auth;
  }

  // Override the default offsetGet function.
  public function offsetGet ($offset)
  {
    return $this->getUser($offset);
  }

  /**
   * Add a new user.
   *
   * @param Array   $rowdef     The row definition.
   * @param String  $password   The raw password for the user.
   * @param Bool    $return     If True, return the new user object.
   *
   * @return Mixed              Results depend on value of $return
   *
   *   The returned value will be False if the login field was not
   *   properly specified in the $rowdef.
   *
   *   If $return is True, then given a correct login field, we will return 
   *   either a User row object, or Null, depending on if the row creation
   *   was successful.
   *
   *   If $return is False, and we have a correct login field, we will simply
   *   return True, regardless of if the row creation succeeded or not.
   */
  public function addUser ($rowdef, $password, $return=False)
  {
    // We allow overriding the login, token, and hash fields.
    $lfield = $this->login_field;
    $tfield = $this->token_field;
    $hfield = $this->hash_field;

    if (!isset($rowdef[$lfield]))
      return False; // The login field is required!

    // Generate a unique token.
    $token = hash($this->hashType, time());
    $rowdef[$tfield] = $token;

    // Generate the password hash.
    $auth = $this->get_auth();
    $hash = $auth->generate_hash($token, $password);
    $rowdef[$hfield] = $hash;

    // Create the user.
    $user = $this->newChild($rowdef);
    $user->save();

    // Return the created user if requested.
    if ($return)
    {
      return $user;
    }

    return True;
  }

}
