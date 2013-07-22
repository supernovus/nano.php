<?php

namespace Nano4\Controllers;

/**
 * A Trait that handles translatable text strings, status messages,
 * and adds the HTML template handler for Views (also with translatable text.)
 */

trait Messages
{
  protected $text;              // Our translation table.
  protected $lang;              // Our language.

  // A list of default status message types.
  protected $status_types = array
  (
    'default' => array('class'=>'message', 'prefix'=>'msg.'),
    'error'   => array('class'=>'error',   'prefix'=>'err.'),
    'warning' => array('class'=>'warning', 'prefix'=>'warn.'),
  );

  protected function __construct_messages_controller ($opts=[])
  {
    if (!isset($this->lang))
    {
      $nano = \Nano4\get_instance();
      $this->lang = $nano['default_language'];
    }

    // Let's load our translation table, and page title.
    $name = $this->name();
    $trconf = $nano->conf->translations;
    $trns   = array($name, 'common');
    $translations = new \Nano4\Utils\Translation($trconf, $trns, $lang);
    $this->text = $translations;
    $this->data['text'] = $translations;

    // Register a view helper for use in our views.
    $html_opts = array
    (
      'translate' => $translations,
    );
    if (property_exists($this, 'html_includes') && isset($this->html_includes))
    {
      $html_opts['include'] = $this->html_includes;
    }
    $this->data['html'] = new \Nano4\Utils\HTML($html_opts);

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

    // And a wrapper to our has_errors() method.
    $this->addWrapper('has_errors');

  }

  // Return our translation object.
  public function get_text ()
  {
    return $this->text;
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

    // Handle 
    if (isset($opts['actions']))
    {
      $actions = array();
      foreach ($opts['actions'] as $aid => $adef)
      {
        if (is_array($adef))
          $action = $adef;
        else
          $action = array();

        if (!isset($action['name']))
        {
          if (is_numeric($aid) && is_string($adef))
            $action['name'] = $adef;
          elseif (is_string($aid))
            $action['name'] = $aid;
          else
            throw new \Exception("Status action requires a name.");
        }

        if (isset($action['ns']))
          $ns = $action['ns'];
        else
          $ns = 'action.';

        $action['text'] = $this->text->getStr($ns.$action['name'], $action);

        if (!isset($action['type']))
          $action['type'] = $this->status_action_type; 

        $actions[] = $action;
      }
      $message['actions'] = $actions;
    }

    if (isset($opts['session']) && $opts['session'])
    {
      $nano = \Nano4\get_instance();
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

}

