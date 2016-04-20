<?php

namespace Example\Models;

/**
 * Users model.
 *
 * We derive from the Nano4 MongoDB Users model, and simply override the
 * child class to be our own, which is defined below.
 *
 * You can customize the Users class more if required for your needs.
 */
class Users extends \Nano4\Models\Mongo\Users 
{
  protected $childclass = '\Example\Models\User';
}

/**
 * User model.
 *
 * A couple samples of overriding the default functionality are given below.
 */
class User extends \Nano4\Models\Mongo\User
{
  /**
   * For this sample, we have an Array of Mongo ObjectID references called
   * 'roles'. When converting to JSON, the current behaviour in the
   * upstream library is terrible and simply turns it into a blank string.
   * So we're converting it to the hexidecimal string instead.
   * Note: we'll leave it up to you to figure out how to implement the Roles
   * class itself if you even need it. Feel free to completely change this
   * method, and the corresponding to_bson() method below.
   */
  public function to_array ($opts=[])
  {
    $array = parent::to_array($opts);
    if (isset($array['roles']))
    {
      $roles = [];
      foreach ($array['roles'] as $clientId)
      {
        $roles[] = (string)$clientId;
      }
      $array['roles'] = $roles;
    }
    return $array;
  }

  /**
   * The reverse of the above method: convert back to an ObjectID when saving.
   * If you replace the to_array() method, make sure those changes are
   * reflected in this one, to ensure things get saved properly.
   */
  public function to_bson ($opts=[])
  { // The BSON bindings are pretty good, but we want to make sure the
    // roles list if it exists, is turned back into it's proper form.
    $data = $this->data;
    if (isset($data['roles']))
    {
      $roles = [];
      foreach ($data['roles'] as $client)
      {
        if (is_string($client))
          $roles[] = new \MongoDB\BSON\ObjectID($client);
        elseif ($client instanceof \MongoDB\BSON\ObjectID)
          $roles[] = $client;
      }
      $data['roles'] = $roles;
    }
    return $data;
  }
}

