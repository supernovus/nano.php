<?php

/* Some quick routines for authorizing users. */

function check_authorization ($user, $token, $uhash, $ahash)
{
  $chash = sha1($user.$token.$uhash);
  if (strcmp($ahash, $chash) == 0)
    return True;
  return False;
}

function login_authorization ($user, $pass, $uhash)
{
  $chash = sha1(trim($user.$pass));
  if (strcmp($uhash, $chash) == 0)
  {
    // We logged in successfully.
    $token = time();
    $ahash = sha1($user.$token.$uhash);
    $auth = array();
    $auth['user']  = $user;
    $auth['uhash'] = $uhash;
    $auth['ahash'] = $ahash;
    $auth['token'] = $token;
    return $auth;
  }
  return False;
}

## End of library.
