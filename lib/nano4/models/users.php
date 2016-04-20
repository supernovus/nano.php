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
  use \Nano4\DB\Model\Compat, Common\Users;

  protected $childclass  = '\Nano4\Models\User';
  protected $resultclass = '\Nano4\DB\ResultSet';

  protected $user_cache  = [];      // A cache of known users.

  /**
   * Get a user.
   *
   * @param Mixed $identifier    Either the numeric primary key,
   *                             or a stringy login field (default 'email')
   *
   * @param Str $column          (optional) An explicit database column.
   *
   * @return Mixed               Either a User row object, or Null.
   */
  public function getUser($identifier, $column=null)
  {
    // First, check to see if it's cached.
    if (isset($this->user_cache[$identifier]))
    {
      return $this->user_cache[$identifier];
    }

    // Look up the user in the database.
    if (isset($column))
    {
      return $this->user_cache[$identifier]
           = $this->getRowByField($column, $identifier);
    }
    elseif (is_numeric($identifier))
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

  /**
   * Get a list of users.
   */
  public function listUsers ($fields=[])
  {
    if (count($fields) == 0)
    {
      $fields[] = $this->primary_key;
      $fields[] = $this->login_field;
    }
    return $this->select(['cols'=>$fields]);
  }

}

