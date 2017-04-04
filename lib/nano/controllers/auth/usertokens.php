<?php

/**
 * Manage User Auth Tokens controller trait.
 *
 * Add to controllers which should be able to manage tokens for users.
 *
 * Requires the JSONResponse trait to be included as well.
 */

namespace Nano\Controllers\Auth;

trait UserTokens
{
  /** 
   * Depending on the content of the POST it can be either a regenerate
   * request from an already authenticated user, or a request to retreive
   * a token by passing login values. Determine which and do it.
   */
  public function handle_post_token ($opts)
  { 
    $lfield = $this->get_prop('username_field', 'user');
    $pfield = $this->get_prop('password_field', 'pass');

    if (isset($opts[$lfield], $opts[$pfield]))
    { // Login and password fields set, pass to the handler method.
      return $this->token_login($opts[$lfield], $opts[$pfield], $opts);
    }

    // No login/password fields set, regenerate our own user's token.

    if (!$this->get_auth($opts))
    { // No auth, cannot continue.
      return $this->json_err('no_auth');
    }

    if (isset($this->user))
    { // A user is logged in, let's (re)generate a token for them.
      return $this->regenerate_token($this->user, $opts);
    }
    else
    { // No user logged in, this method is not usable.
      return $this->json_err('no_user');
    }
  }

  /**
   * Get a token for the currently logged in user.
   */
  public function handle_get_token ($opts)
  {
    if (!$this->get_auth($opts))
    {
      return $this->json_err('no_auth');
    }
    if (!isset($this->user))
    {
      return $this->json_err('no_user');
    }

    return $this->get_token($this->user, $opts);
  }

  /**
   * Expire a token for the currently logged in user.
   */
  public function handle_expire_token ($opts)
  {
    if (!$this->get_auth($opts))
    {
      return $this->json_err('no_auth');
    }
    if (!isset($this->user))
    {
      return $this->json_err('no_user');
    }

    return $this->expire_token($this->user, $opts);
  }  

  /**
   * Get a token for a specified user (admin or ipaccess only.)
   */
  public function handle_get_user_token ($opts)
  {
    $user = $this->get_requested_user($opts);
    if (is_string($user))
    { // An error message was returned.
      return $this->json_err($user);
    }
    elseif (is_object($user))
    { // A user was returned.
      return $this->get_token($user, $opts);
    }
    else
    { // No idea what was returned.
      return $this->json_err(['internal_error','invalid_user_data']);
    }
  }

  /**
   * Expire a token for a specified user (admin or ipaccess only.)
   */
  public function handle_expire_user_token ($opts)
  {
    $user = $this->get_requested_user($opts);
    if (is_string($user))
    { // An error message was returned.
      return $this->json_err($user);
    }
    elseif (is_object($user))
    { // A user was returned.
      return $this->expire_token($user, $opts);
    }
    else
    { // No idea what was returned.
      return $this->json_err(['internal_error','invalid_user_data']);
    }
  }


  /**
   * (Re)generate a token for a specified user (admin or ipaccess only.)
   */
  public function handle_post_user_token ($opts)
  {
    $user = $this->get_requested_user($opts);
    if (is_string($user))
    { // An error message was returned.
      return $this->json_err($user);
    }
    elseif (is_object($user))
    { // A user was returned.
      return $this->regenerate_token($user, $opts);
    }
    else
    { // No idea what was returned.
      return $this->json_err(['internal_error','invalid_user_data']);
    }
  }

  protected function get_requested_user ($opts)
  {
    if (!$this->get_auth($opts))
    {
      return 'no_auth';
    }
    
    if (isset($this->user))
    { // A user exists, make sure they are an admin.
      $isAdmin = false;
      if (is_callable([$this->user, 'isAdmin']))
      {
        if ($user->isAdmin())
        {
          $isAdmin = true;
        }
      }
      elseif (is_callable([$this, 'is_admin']))
      {
        if ($this->is_admin($user))
        {
          $isAdmin = true;
        }
      }
      if (!$isAdmin)
      {
        return 'not_authorized';
      }
    }

    $umodel = $this->get_prop('users_model',  'users');
    $ufield = $this->get_prop('userid_field', 'uid');
    if (isset($opts[$ufield]))
    {
      $uid = $opts[$ufield];
      $users = $this->model($umodel);
      $user = $users->getUser($uid);
      if ($user)
      {
        return $user;
      }
      return 'invalid_uid';
    }
    return 'missing_uid';
  }

  protected function get_user_token ($user, $includeModel=false)
  {
    $tmodel = $this->get_prop('auth_token_model', 'auth_tokens');
    $tokens = $this->model($tmodel);
    $token  = $tokens->getUserToken($user);
    if ($includeModel)
      return [$tokens, $token];
    else
      return $tokens->getUserToken($token);
  }

  protected function get_token ($user, $opts)
  {
    $token = $this->get_user_token($user);
    if ($token)
    { // A token exists, return it's App Token string.
      $tstr = $token->appToken($user);
      return $this->json_ok(['token'=>$tstr]);
    }
    else
    {
      return $this->json_err('no_token');
    }
  }

  protected function expire_token ($user, $opts)
  {
    $token = $this->get_user_token($user);
    if ($token)
    {
      $token->expireNow();
    }
    return $this->json_ok();
  }

  protected function new_token ($user, $opts)
  {
    $newdef = ['user'=>$user];
    if (isset($opts['expire']))
      $newdef['expire'] = $opts['expire'];
    return $tokens->newToken($newdef);
  }

  protected function regenerate_token ($user, $opts)
  {
    list($tokens, $token) = $this->get_user_token($user, true);
    if ($token)
    { // A token exists, let's regenerate it and return the new app token.
      $expire = isset($opts['expire']) ? $opts['expire'] : null;
      $token->regenerate($expire);
    }
    else
    {
      $token = $this->new_token($user, $opts);
      if (!$token)
      {
        return $this->json_err('could_not_create_token');
      }
    }
    $tstr = $token->appToken($user);
    return $this->json_ok(['token'=>$tstr]);
  }

  protected function login_token ($login, $pass, $opts)
  {
    $umodel = $this->get_prop('users_model', 'users');
    $users = $this->model($umodel);
    $user = $users->getUser($login);
    if (!$user)
    {
      return $this->json_err('invalid_user');
    }
    $userhash  = $user->hash;
    $usertoken = $user->token;
    $auth = $users->get_auth();
    if ($auth->check_credentials($usertoken, $pass, $userhash))
    { // The user authenticated, so let's get or generate a token.
      $token = $this->get_user_token($user);
      if (!$token)
      { // No token, generate one.
        $token = $this->new_token($user, $opts);
        if (!$token)
        {
          return $this->json_err('could_not_create_token');
        }
      }
      $tstr = $token->appToken($user);
      return $this->json_ok(['token'=>$tstr]);
    }
    return $this->json_err('no_auth');
  }

}