<?php

namespace Nano4\Models\Mongo;
use \MongoDB\BSON\ObjectID;

/**
 * Users (MongoDB) base class, as used by our Controllers\Auth trait.
 *
 * This is a bare minimum, and should be extended in your application models.
 *
 * By default we expect the 'email' field to be used as the plain text login.
 * You can change that to 'username' or something else if you so desire, or
 * override the getUser() method to support multiple fields. Just remember that
 * it MUST match the primary key as one of the fields.
 */

class Users extends \Nano4\DB\Mongo\Model
{
  use \Nano4\Models\Common\Users;

  protected $childclass  = '\Nano4\Models\Mongo\User';
  protected $resultclass = '\Nano4\DB\Mongo\Results';

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
           = $this->findOne([$column=>$identifier]);
    }
    elseif ($identifier instanceof ObjectID || ctype_xdigit($identifier))
    { // Look up by userId.
      return $this->user_cache[$identifier] 
           = $this->getDocById($identifier);
    }
    else
    { // Look up by e-mail address.
      $field = $this->login_field;
      return $this->user_cache[$identifier] 
           = $this->findOne([$field=>$identifier]);
    }
  }

  /**
   * Get a list of users.
   */
  public function listUsers ($fields=[])
  {
    if (count($fields) == 0)
    {
      $fields[$this->login_field] = true;
    }
    return $this->find([], ['projection'=>$fields]);
  }

}

