<?php

namespace ExampleApp\Models;
use Nano3\Base\DB;

/**
 * Users model. Represents the 'users' table in the database.
 */
class Users extends DB\Model
{
  protected $childclass  = '\ExampleApp\Models\User';
  protected $resultclass = '\Nano3\Base\DB\ResultSet';

  public function getUser ($identifier)
  {
    if (is_numeric($identifier))
    {
      return $this->getRowById($identifier);
    }
    else
    {
      return $this->getRowByField('email', $identifier);
    }
  }

  public function newUser ($email, $name, $pass, $return=False)
  { // Extend as needed.

    // Generate a unique user token.
    $token = sha1(time());
    $hash  = \Nano3\Utils\SimpleAuth::generate_hash($token, $pass);
    $row = array
    (
      'email'  => $email,
      'name'   => $name,
      'token'  => $token,
      'hash'   => $hash,
    );
    $this->newRow($row);
    if ($return)
    {
      return $this->getUser($email);
    }
  }

}

/**
 * User model. Represents an individual user in the Users model.
 */
class User extends DB\Item
{
  public function changePassword ($newpass, $autosave=True)
  {
    $this->hash = \Nano3\Utils\SimpleAuth::generate_hash
      ($this->token, $newpass);
    if ($autosave) $this->save();
  }

  public function changeEmail ($newemail, $autosave=True)
  {
    if ($this->parent->getUser($newemail))
    {
      return Null; // Sorry, e-mail already in use.
    }
    $this->email = $newemail;
    if ($autosave) $this->save();
  }
}

