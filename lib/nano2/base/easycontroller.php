<?php

/* EasyController: An extension of CoreController that adds a lot of
   extra functionality that makes it more useful.
 */

$nano = get_nano_instance();
$nano->loadBase('corecontroller');
$nano->loadUtil('session');

// Use this to return a password hash.
function easy_password_hash ($user, $pass)
{
  return sha1(trim($user.$pass));
}

abstract class EasyController extends CoreController
{
  protected $data;         // Our data to send to the templates.
  protected $screen;       // Set if needed, otherwise uses $this->name().
  protected $model_opts;   // Options to pass to load_model(), via model().

  // Display our screen.
  public function display ($data=null, $screen=null)
  {
    if (isset($data) && is_array($data))
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data += $this->data;
      }
    }
    else
    {
      if (isset($this->data) && is_array($this->data))
      {
        $data = $this->data;
      }
      else
      {
        $data = array();
      }
    }
    if (is_null($screen))
    { if (isset($this->screen))
        $screen = $this->screen;
      else
        $screen = $this->name();
    }
    return $this->process_template($screen, $data);
  }

  // Return a model object. We will load these on demand.
  protected function model ($model, $opts=array())
  {
    if (!isset($this->models[$model]))
    { // No model has been loaded yet.
      if (isset($this->model_opts) && is_array($this->model_opts))
      { // We have model options in the controller.
        $found_options = false;
        if (isset($this->model_opts['common']))
        { // Common options used by all models.
          $opts += $this->model_opts['common'];
          $found_options = true;
        }
        if (isset($this->model_opts[$model]))
        { // There is model-specific options.
          $opts += $this->model_opts[$model];
          $found_options = true;
        }
        if (!$found_options)
        { // No model-specific or common options found.
          $opts += $this->model_opts;
        }
      }
      $this->load_model($model, $opts);
    }
    return $this->models[$model];
  }

  // get_user_info() must return an assoc array that contains at least a 'hash'
  // member, representing the SHA1 of the userid and password concatted.
  abstract protected function get_user_info ($userid);

  protected $login_url = '/login'; // A page to redirect to for login purposes.

  public function get_user ($redirect=false, $adddata=true)
  {
    $lastpath = get_path();
    puts('auth.lastpath', $lastpath);

    $userid = gets('auth.user');
    $token  = gets('auth.token');
    $ahash  = gets('auth.hash');

#    error_log("u: '$userid', t: '$token', h: '$ahash'");

    if (!isset($userid) || !isset($token) || !isset($ahash))
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $user = $this->get_user_info($userid);

#    error_log("checking user");

    if (!$user)
    {
      if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

    $uhash = $user['hash'];

#    error_log("checking authorization");

    $chash = sha1($userid.$token.$uhash);
    if (strcmp($ahash, $chash) != 0)
    { if ($redirect)
        return $this->redirect($this->login_url);
      else
        return false;
    }

#    error_log("we succeeded.");

    if ($adddata)
      $this->data['user'] = $user;

    return $user;
  }

  // If successful this will redirect. If not, it will return false.
  protected function process_login ($user, $pass)
  {
    $uconf = $this->get_user_info($user);
    if (!$uconf)
    { //error_log("user config was invalid: ".json_encode($uconf));
      return False;
    }

    $uhash = $uconf['hash'];

    $chash = easy_password_hash($user, $pass);
    if (strcmp($uhash, $chash) == 0)
    {
      // We logged in successfully.
      $token = time();
      $ahash = sha1($user.$token.$uhash);
    }
    else
      return False;

    puts('auth.user',  $user);
    puts('auth.token', $token);
    puts('auth.hash',  $ahash);

    $lastpath = gets('lastpath', 'AUTH');

    if (!isset($lastpath) || $lastpath == '' || $lastpath == '/login')
      $lastpath = '/';

    error_log("User '$user' logged in.");

    $this->redirect($lastpath);
  }

  protected function process_logout ()
  {
    $user = gets('auth.user');
    error_log("User '$user' logged out.");
    $_SESSION = array();
    if (ini_get("session.use_cookies"))
    { $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params["path"],   $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    session_destroy();
    $this->redirect();
  }

}

// End of base class.

