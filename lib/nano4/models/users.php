<?php

namespace Nano4\Models;

/**
 * Users (DB table) base class, as used by our Controllers\Auth trait.
 *
 * This is a bare minimum, and should be extended in your application models.
 *
 * By default we expect the 'email' field to be used as the plain text login.
 * You can change that to 'username' or something else if you so desire, or
 * override the getUser() method to support multiple fields. Just remember that
 * it MUST match the primary key as one of the fields.
 */

class Users extends \Nano4\DB\Model
{
  protected $childclass  = '\Nano4\Models\User';
  protected $resultclass = '\Nano4\DB\ResultSet';

  protected $login_field = 'email'; // The unique stringy DB field.
  protected $token_field = 'token'; // The user token field.
  protected $hash_field  = 'hash';  // The authentication hash field.

  protected $user_cache  = [];      // A cache of known users.

  /**
   * Get a user.
   *
   * @param Mixed $identifier    Either the numeric primary key,
   *                             or a stringy login field (default 'email')
   * 
   * @return Mixed               Either a User row object, or Null.
   */
  public function getUser($identifier)
  {
    // First, check to see if it's cached.
    if (isset($this->user_cache[$identifier]))
    {
      return $this->user_cache[$identifier];
    }

    // Look up the user in the database.
    if (is_numeric($identifier))
    { // Look up by userId.
      return $this->user_cache[$identifier] 
           = $this->getRowById($identifier);
    }
    else
    { // Look up by e-mail address.
      $field = $this->login_field;
      return $this->user_cache[$identifier] 
           = $this->getRowByField($field, $identifier);
    }
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
    $token = sha1(time());
    $rowdef[$tfield] = $token;

    // Generate the password hash.
    $hash = \Nano4\Utils\SimpleAuth::generate_hash($token, $password);
    $rowdef[$hfield] = $hash;

    // Create the user.
    $this->newRow($rowdef);

    // Return the created user if requested.
    if ($return)
    {
      $identifier = $rowdef[$lfield];
      return $this->getUser($identifier);
    }

    return True;
  }
}

