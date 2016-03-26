<?php

namespace Nano4\Models\Common;

trait User
{
  abstract public function save ($opts=[]);
  abstract public function get_id ();

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
    $tfield = $this->parent->token_field();
    $hfield = $this->parent->hash_field();
    $this->$tfield = hash($hash, time());
    $this->$hfield = $auth->generate_hash($this->token, $newpass);
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
    $rfield = $this->parent->reset_field();
    $reset = $this->$rfield = uniqid(base64_encode($this->id), True);
    if ($autosave) $this->save();
    return $reset;
  }

  /**
   * Change our login field, ensuring that it is unique.
   *
   * @param String $newlogin     The new login value.
   * @param Bool   $autosave     Save automatically (default True)
   *
   * @return Bool                False means e-mail address already in use.
   *                             True means we updated successfully.
   */
  public function changeLogin ($newlogin, $autosave=True)
  {
    $lfield = $this->parent->login_field();
    if ($this->parent->getUser($newlogin, $lfield))
    {
      return False; // Sorry, e-mail already in use.
    }
    $this->$lfield = $newemail;
    if ($autosave) $this->save();
    return True;
  }

}
