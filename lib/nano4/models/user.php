<?php

namespace Nano4\Models;

/**
 * User (DB row) base class, as used by our Controllers\Auth trait.
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

class User extends \Nano4\DB\Item
{
  /**
   * Change our password.
   *
   * @param String $newpass      The new password
   * @param Bool   $autosave     Save automatically (default True)
   */
  public function changePassword ($newpass, $autosave=True)
  { // We auto-generate a unique token every time we change the password.
    $hash = $this->parent->hash_type();
    $auth = $this->parent->get_auth();
    $this->token = hash($hash, time());
    $this->hash = $auth->generate_hash($this->token, $newpass);
    if ($autosave) $this->save();
  }

  /** 
   * Reset our reset code to a unique value.
   *
   * @param Bool $autosave    Save automatically (default True)
   *
   * @return String           The new reset code is returned.
   */
  public function resetReset ($autosave=True)
  {
    $this->reset = uniqid(base64_encode($this->id), True);
    if ($autosave) $this->save();
    return $this->reset;
  }

  /**
   * Change our e-mail address, ensuring that it is unique.
   * This is only required in a user model where every user must have
   * a unique e-mail address, such is the case if the e-mail address is
   * used as a login field.
   *
   * @param String $newemail     The new email address.
   * @param Bool   $autosave     Save automatically (default True)
   *
   * @return Bool                False means e-mail address already in use.
   *                             True means we updated successfully.
   */
  public function changeEmail ($newemail, $autosave=True)
  {
    if ($this->parent->getRowByField('email', $newemail))
    {
      return False; // Sorry, e-mail already in use.
    }
    $this->email = $newemail;
    if ($autosave) $this->save();
    return True;
  }

}

