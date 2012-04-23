<?php

/**
 * Advanced controller.
 *
 * Provides far more functionality than the Basic controller.
 * Including:
 *
 *  - Authenticated users using SimpleAuth.
 *  - Translations, using the Translation class.
 *  - A view loader for e-mail messages.
 *  - Screen messages such as errors, warnings, etc.
 *  - Plenty more.
 *
 * You'll need to set the following constants in your app:
 *
 *   PAGE_DEFAULT
 *   PAGE_LOGIN
 *   LAYOUT_DEFAULT
 *
 */

namespace Nano3\Base\Controllers;

abstract class Advanced extends Basic
{
  protected $save_uri  = True;  // Set to False for login/logout pages.
  protected $need_user = True;  // Set to False for non-user pages.
  protected $user;              // This will be set on need_user pages.
  protected $text;              // Our translation table.
  protected $lang;              // Our language.
  protected $shown = array();   // If an id exists in shown, don't show again.

  // Paths to find e-mail message templates in.
  protected $message_path = array('views/messages', 'views/nano/messages');

  // A list of default status message types.
  protected $status_types = array
  (
    'default' => array('class'=>'message', 'prefix'=>'msg.'),
    'error'   => array('class'=>'error',   'prefix'=>'err.'),
    'warning' => array('class'=>'warning', 'prefix'=>'warn.'),
  );
  protected $html_includes;     // Override in your controller base.
  protected $load_scripts;      // Override in your individual controllers.

  // Construct our object.
  public function __construct ($opts=array()) 
  {
    $nano = \Nano3\get_instance();
    if (!isset($this->default_url))
    {
      $this->default_url = PAGE_DEFAULT;
    }
    if (isset($nano->conf->models))
    {
      $this->model_opts = $nano->conf->models;
    }
    if (!isset($this->layout))
    {
      $this->layout = LAYOUT_DEFAULT;
    }
    if ($this->save_uri)
    {
      $nano->sess->lasturi = $this->request_uri();
    }
    $lang = DEFAULT_LANG;
    if ($this->need_user)
    {
      $auth = \Nano3\Utils\SimpleAuth::getInstance();
      $userid = $auth->is_user();
      if ($userid)
      {
        $users = $this->model('users');
        $user  = $users->getUser($userid);
        if (!$user) 
        { 
          $this->redirect(PAGE_LOGIN); 
        }
        if (method_exists($this, 'validate_user'))
        {
          $this->validate_user($user);
        }
        $this->user = $user;
        $this->data['user'] = $user;
        if (isset($user->lang) && $user->lang)
        {
          $lang = $user->lang;
        }
      }
      else
      {
        $this->redirect(PAGE_LOGIN);
      }
    }
    $this->lang = $lang;

    // Let's load our translation table, and page title.
    $name = $this->name();
    $trconf = $nano->conf->translations;
    $trns   = array($name, 'common');
    $translations = new \Nano3\Utils\Translation($trconf, $trns, $lang);
    $this->text = $translations;
    $this->data['text'] = $translations;

    // Register a view helper for use in our views.
    $html_opts = array
    (
      'translate' => $translations,
    );
    if (isset($this->html_includes))
    {
      $html_opts['include'] = $this->html_includes;
    }
    $this->data['html'] = new \Nano3\Utils\HTML($html_opts);

    // Next, let's set the languages for the messages.
    $nano->messages->dir = array();
    foreach ($this->message_path as $dir)
    {
      $nano->messages->dir[] = $dir . '/' . $lang;
    }

    // Okay, first look for the 'title' key. If it's set, typically in the
    // translation table for this namespace, we'll use it.
    $pagetitle = $translations->getStr('title');
    if ($pagetitle == 'title')
    { // If we didn't find the page title, use a default value.
      $pagetitle = ucfirst($name);
    }
    $this->data['title'] = $pagetitle;

    // Process any messages that may be in the session.
    if (isset($nano->sess->messages))
    {
      $this->data['messages'] = $nano->sess->messages;
      unset($nano->sess->messages);
      $this->data['has_status'] = array();
      foreach ($this->data['messages'] as $msg)
      {
        $this->data['has_status'][$msg['class']] = True;
      }
    }

    // Add any javascript files we want available to the $scripts var.
    if (is_array($this->load_scripts))
    {
      $this->add_js($this->load_scripts);
    }

    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';

    // And a wrapper to our has_errors() method.
    $this->addWrapper('has_errors');

    // Create a hook for apps that need to do more on controller construction.
    // The hook is passed a copy of the controller being constructed.
    $nano->callHook('Controller.construct', array($this));
  }

  // Get a user.
  public function get_user ()
  {
    if (isset($this->user))
    {
      return $this->user;
    }
  }

  // Add a message to the stack.
  public function message ($name, $opts=array())
  {
    // Get some default types.
    if (isset($opts['type']))
    {
      $opts += $this->status_types[$opts['type']];
    }
    else
    {
      $opts += $this->status_types['default'];
    }

    $class = $opts['class'];

    if (!is_numeric(strpos(':', $name)))
    {
      $prefix = $opts['prefix'];
    }
    else
    {
      $prefix = '';
    }

    $text = $this->text->getStr($prefix.$name, $opts);

    $message = array('name'=>$name, 'class'=>$class, 'text'=>$text);

    if (isset($opts['session']) && $opts['session'])
    {
      $nano = \Nano3\get_instance();
      if (isset($nano->sess->messages))
      {
        $messages = $nano->sess->messages;
      }
      else
      {
        $messages = array();
      }
      $messages[] = $message;
      $nano->sess->messages = $messages;
    }
    else
    {
      if (!isset($this->data['messages']))
      {
        $this->data['messages'] = array();
      }
      $this->data['messages'][] = $message;
      if (!isset($this->data['has_status']))
      {
        $this->data['has_status'] = array();
      }
      $this->data['has_status'][$message['class']] = True;
    }
  }

  // Redirect to another page, and show a message.
  public function redirect_msg ($name, $url=Null, $opts=array())
  {
    $opts['session'] = True;
    $this->message($name, $opts);
    $this->redirect($url, $opts);
  }

  // Add an error to the stack.
  public function error ($name, $opts=array())
  {
    $opts['type'] = 'error';
    $this->message($name, $opts);
  }

  // Use this when you want to return the display immediately.
  public function show_error ($name, $opts=array())
  {
    $this->error($name, $opts);
    return $this->display();
  }

  // Use this when you want to redirect to another page, and show the error.
  public function redirect_error ($name, $url=Null, $opts=array())
  {
    $opts['type']  = 'error';
    $this->redirect_msg($name, $url, $opts);
  }

  // Add a warning to the stack.
  public function warning ($name, $opts=array())
  {
    $opts['type']  = 'warning';
    $this->message($name, $opts);
  }

  // Check to see if we have any of a certain class of status  messages.
  public function has_status ($type)
  {
    if (isset($this->data['has_status']))
    {
      $has = $this->data['has_status'];
      if (isset($has[$type]))
      {
        return $has[$type];
      }
    }
    return False;
  }

  // Wrapper for the above checking for errors.
  public function has_errors ()
  {
    return $this->has_status('error');
  }

  // Get model options for models identified as 'db' models.
  protected function get_db_model_opts ($model, $opts)
  {
#    error_log("get_db_model_opts($model)");
    $nano = \Nano3\get_instance();
    if (isset($nano->conf->db))
    {
      return $nano->conf->db;
    }
  }

  /**
   * Check for a required option in an array.
   * The option must exist, and it must not be empty or just whitespace.
   * 
   * Useful for GET, POST and REQUEST options, and other data.
   * It can handle missing options in various ways.
   */
  public function need ($what, $array, $opts=array())
  {
    // We support different return types.
    $return_value  = 0;
    $return_bool   = 1;
    $return_status = 2;

    // If we're using the 'status' return type, these are the statuses.
    $status_good    = 1;  //  1 = Item was found, and is valid.
    $status_missing = 0;  //  0 = Item was not found.
    $status_invalid = -1; // -1 = Item was found, but is not valid.

    if (isset($opts['getstatus']) && $opts['getstatus'])
    {
      $return = $return_status;
    }
    elseif (isset($opts['bool']) && $opts['bool'])
    {
      $return = $return_bool;
    }
    else
    {
      $return = $return_value;
    }

    $found = False;
    $valid = False;

    if (isset($array[$what]) && trim($array[$what]) != '')
    {  // We found the item.
      $found = True;
      if (isset($opts['valid_values']))
      { // Look for the value in an array of values.
        if (is_numeric(array_search($array[$what], $opts['valid_values'])))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_keys']))
      { // See if the value is a key in an array.
        if (array_key_exists($array[$what], $opts['valid_keys']))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_match']))
      { // See if the value matches a regular expression.
        if (preg_match($opts['valid_match'], $array[$what]))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_check']))
      { // Use a callback function to validate the value.
        // The value to be checked will be sent as the first parameter,
        // and the name of the field ($what) will be sent as the second.
        // It must return a boolean value representing the validity of the
        // value in question.
        $callback = $opts['valid_check'];
        if (is_callable($callback))
        {
          $valid = call_user_func($callback, $array[$what], $what);
        }
      }
      else
      { // No validation rules exist, therefore the value is valid.
        $valid = True;
      }
    }

    // Messages and such default to errors.
    $show_msg = True;
    $msg_type = 'error';
    $showid   = Null;

    if (isset($opts['show_msg']))
    {
      $show_msg = $opts['show_msg'];
    }

    if (isset($opts['msg_type']))
    {
      $msg_type = $opts['msg_type'];
    }

    if ($found && $valid)
    {
      if ($return == $return_bool)
        return True;
      elseif ($return == $return_status)
      {
        return $status_good;
      }
      else
        return $array[$what];
    }
    elseif (!$found)
    {
      $status   = $status_missing;
      $msg_name = 'needed_field';
      if (isset($opts['need_msg']))
      {
        $msg_name = $opts['need_msg'];
      }
      if (isset($opts['need_type']))
      {
        $msg_type = $opts['need_type'];
      }
      if (isset($opts['show_need']))
      {
        $show_msg = $opts['show_need'];
      }
    }
    elseif (!$valid)
    {
      $status   = $status_invalid;
      $msg_name = 'invalid_value';
      if (isset($opts['invalid_msg']))
      {
        $msg_name = $opts['invalid_msg'];
      }
      if (isset($opts['invalid_type']))
      {
        $msg_type = $opts['invalid_type'];
      }
      if (isset($opts['show_invalid']))
      {
        $show_msg = $opts['show_invalid'];
      }
    }

    if (isset($opts['show_id']))
    { // If show_id is set, only one message with the given id will
      // be displayed. This prevents duplicate messages from building up.
      $showid = $opts['show_id'];
      if (isset($this->shown[$showid]) && $this->shown[$showid])
      {
        $show_msg = False;
      }
    }

    if ($show_msg)
    {
      $fieldname = $this->text[$what];
      $msg_opts  = array('type'=>$msg_type, 'reps'=>array($fieldname));
      $this->message($msg_name, $msg_opts);
      if (isset($showid))
      {
        $this->shown[$showid] = True;
      }
    }

    // If we reached here, the field was invalid or not found.
    if ($return == $return_bool)
    {
      return False;
    }
    elseif ($return == $return_status)
    {
      return $status;
    }
    else
    {
      return Null;
    }
  }

  /**
   * Take an array of values to search for, and an array to search
   * for them in, and do the rest automagically.
   *
   * Depending on the categorize option, the output will be one of
   * two forms. If categorize is False or not specified, then we will
   * return an associative array where the key is the name of the
   * member, and the value is one of:
   *
   *   1 = Data was found, and is valid.
   *   0 = Data was not found.
   *  -1 = Data was found, but is not valid.
   *
   * If 'categorize' is specified and is True, then we will return
   * an assocative array with three associative arrays inside it:
   *
   *  'correct'   Will contain keys for valid fields, and the valid value.
   *  'invalid'   Will contain keys for invalid fields, and the invalid value.
   *  'missing'   Will contain keys for missing fields, and a value of True.
   *
   */
  public function needs ($rules, $array, $defopts=array())
  {
    $results = array();
    if (isset($defopts['categorize']) && $defopts['categorize'])
    {
      $categorize = True;
      $results['missing'] = array();
      $results['invalid'] = array();
      $results['correct'] = array();
      unset($defopts['categorize']);
    }
    foreach ($rules as $key => $val)
    {
      if (is_numeric($key))
      { // Use the default options.
        $want = $val;
        $opts = $defopts;
      }
      else
      { // We have specified some form of options.
        $want = $key;
        if (is_array($val))
        {
          $opts = $val;
        }
        elseif (is_bool($val))
        {
          if ($val)
          { // Use the default options.
            $opts = $defopts;
          }
          else
          { // Use the default options, but show no messages.
            $opts = $defopts;
            $opts['show_msg'] = False;
          }
        }
        else
        { // Sorry we didn't recognize your options.
          $opts = $defopts;
        }
      }
      $opts['getstatus'] = True;
      $status = $this->need($want, $array, $opts);
      if ($categorize)
      {
        if ($status == 1)
        {
          $results['correct'][$want] = $array[$want];
        }
        elseif ($status == -1)
        {
          $results['invalid'][$want] = $array[$want];
        }
        elseif ($status == 0)
        {
          $results['missing'][$want] = True;
        }
      }
      else
      { // Return the status code.
        $results[$want] = $status;
      }
    }
    return $results;
  }

  /**
   * Return a list of models given a specific ".type" definition.
   *
   * TODO: Deep type searches.
   */
  public function get_models_of_type ($type, $deep=False)
  {
    $models = array();
    foreach ($this->model_opts as $name => $opts)
    {
      if (substr($name, 0, 1) == '.') continue; // Skip groups.
      if 
      (
        is_string($opts)
        ||
        (is_array($opts) && isset($opts['.type']))
      )
      {
        $modeltype = $opts['.type'];
        if ($modeltype == $type)
        {
          $models[$name] = $opts;
        }
      }
    }
    return $models;
  }

}

