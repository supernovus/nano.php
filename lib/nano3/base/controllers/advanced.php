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

  // Override as required.
  protected $message_path = array('views/messages', 'views/nano/messages');

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

  public function message ($name, $opts=array(), $reps=Null)
  {
    if (isset($opts['class']))
    {
      $class = $opts['class'];
    }
    else
    {
      $class = 'message';
    }
    if (!is_numeric(strpos(':', $name)))
    {
      if (isset($opts['prefix']))
      {
        $prefix = $opts['prefix'];
      }
      else
      {
        $prefix = 'msg.';
      }
    }

    $text = $this->text->getStr($prefix.$name, $reps, $opts);

    $message = array('name'=>$name, 'class'=>$class, 'text'=>$text);

    if (isset($opts['session']) && $opts['session'])
    {
      $nano = \Nano3\get_instance();
      if (!isset($nano->sess->messages))
      {
        $nano->sess->messages = array();
      }
      $nano->sess->messages[] = $message;
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

  public function error ($name, $opts=array(), $reps=Null)
  {
    $opts['class']  = 'error';
    $opts['prefix'] = 'err.';
    $this->message($name, $opts, $reps);
  }

  // Use this when you want to return the display immediately.
  public function show_error ($name, $opts=array(), $reps=Null)
  {
    $this->error($name, $opts=array(), $reps=Null);
    return $this->display();
  }

  // Use this when you want to redirect to another page, and show the error.
  public function redirect_error ($url, $name, $opts=array(), $reps=Null)
  {
    $opts['session'] = True;
    $this->error($name, $opts, $reps);
    $this->redirect($url, $opts);
  }

  public function warning ($name, $opts=array(), $reps=Null)
  {
    $opts['class']  = 'warning';
    $opts['prefix'] = 'warn.';
    $this->message($name, $opts, $reps);
  }

}
