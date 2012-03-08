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
    // You must initialize $nano->conf->db in your application.
    $this->model_opts = $nano->conf->db;
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
    }

    // Add any javascript files we want available to the $scripts var.
    if (is_array($this->load_scripts))
    {
      foreach ($this->load_scripts as $script)
      {
        $this->add_js($script);
      }
    }

    // We want to be able to access the data, via the $data attribute
    // in the views. It makes it easier to pass to components, etc.
    $this->data['__data_alias'] = 'data';

    // Create a hook for apps that need to do more on controller construction.
    // The hook is passed a copy of the controller being constructed.
    $nano->callHook('Controller.construct', array($this));
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

}

