<?php

namespace Nano4\Models\Mongo;

/**
 * User (MongoDB) base class, as used by our Controllers\Auth trait.
 *
 * As with the Users base class, this is a minimum, and should be extended.
 *
 * The user schema must have at least the following fields:
 *
 *  'id'      The primary key, generally a SERIAL auto-incrementing integer.
 *  'reset'   A string containing a reset code, used to reset the password.
 *  'hash'    A string containing the authentication hash.
 *  'token'   A string containing a unique identifier used in the hash.
 *  'email'   A string containing the primary e-mail address for the user.
 *
 * Any other fields are up to you, but those are the ones used by the
 * Controllers\Auth trait, and thus required for the methods below.
 *
 */
class User extends \Nano4\DB\Mongo\Item
{
  use \Nano4\Models\Common\User;  
}

