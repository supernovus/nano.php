<?php

namespace Example\Models;

/**
 * Users model. Represents the 'users' table in the database.
 * We are using the Nano4 default Users model as a base, and can extend
 * it with any application specific stuff in here.
 */
class Users extends \Nano4\Models\Users
{
  protected $childclass  = '\Example\Models\User';
  // The rest of your extensions here.
}

/**
 * User model. Represents an individual user in the Users model.
 * Again, we use the Nano4 default model, and can extend it in here.
 */
class User extends \Nano4\Models\User
{
  // The rest of your extensions here.
}

