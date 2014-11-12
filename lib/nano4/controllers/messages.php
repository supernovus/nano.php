<?php

namespace Nano4\Controllers;

/**
 * A Trait that handles translatable text strings, status messages,
 * and adds the HTML template handler for Views (also with translatable text.)
 *
 * If you're going to use the add_status_json() method, you'll need the
 * JSON trait loaded first.
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
    $nano = \Nano4\get_instance();

    if (!isset($this->lang))
    {
      $this->lang = $nano['default_lang'];
    }

    $name = $this->name();

    if (isset($opts['text_object']))
    {
      $translations = $opts['text_object'];
    }
    else
    {
      // Let's load our translation table, and page title.
      $trconf = $nano->conf->translations;
      $trns   = array($name, 'common');
      $translations = new \Nano4\Utils\Translation($trconf, $trns, $this->lang);  
    }
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
    if (!isset($this->data['title']))
    {
      $pagetitle = $translations->getStr('title');
      if ($pagetitle == 'title')
      { // If we didn't find the page title, use a default value.
        $pagetitle = ucfirst($name);
      }
      $this->data['title'] = $pagetitle;
    }

    // Process any messages that may be in the session.
    if (isset($nano->sess->messages))
    {
      $this->data['messages'] = $nano->sess->messages;
      unset($nano->sess->messages);
      $this->data['has_status'] = [];
      $status_keys = [];
      foreach ($this->data['messages'] as $msg)
      {
        $this->data['has_status'][$msg['class']] = True;
        $key = $msg['key'];
        if (isset($status_keys[$key]))
          $status_keys[$key]++;
        else
          $status_keys[$key] = 1;
      }
#      error_log("status keys: '$status_keys'");
      $this->data['has_status_key'] = $status_keys;
    }

    // And a wrapper to our has_errors() method.
    $this->addWrapper('has_errors');

  }

  // Return our translation object.
  public function get_text ()
  {
    return $this->text;
  }

  // An alias for message()
  public function msg ($name, $opts=[])
  {
    return $this->message($name, $opts);
  }

  // Add a message to the stack.
  public function message ($name, $opts=[])
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

    if (is_array($name))
    { // Complex structure, we must do further processing.
      if (isset($name['key']))
      { // Associative array format.
        if (isset($name['args']))
        {
          $opts['reps'] = $name['args'];
        }
        elseif (isset($name['vars']))
        {
          $opts['vars'] = $name['vars'];
        }
        $name = $name['key'];
      }
      elseif (isset($name[0]))
      {
        if (count($name) > 1)
        {
          $opts['reps'] = array_slice($name, 1);
        }
        $name = $name[0];
      }
    }

    if (!is_numeric(strpos(':', $name)))
    {
      $prefix = $opts['prefix'];
    }
    else
    {
      $prefix = '';
    }

    $text = $this->text->getStr($prefix.$name, $opts);

    if (isset($opts['prefix']))
    {
      $text = str_replace($opts['prefix'], '', $text);
    }

    $key = preg_replace('/\s+/', '_', $name);

    $message = ['key'=>$key, 'name'=>$name, 'class'=>$class, 'text'=>$text];

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
      $key = $message['key'];
      if (isset($this->data['has_status_key']))
        $status_keys = $this->data['has_status_key'];
      else
        $status_keys = [];
      if (isset($status_keys[$key]))
        $status_keys[$key]++;
      else
        $status_keys[$key] = 1;
      $this->data['has_status_key'] = $status_keys;
    }
  }

  /**
   * Store any current messages in the session, so they can be retreived
   * on a redirect.
   */
  public function store_messages ()
  {
    if (isset($this->data['messages']) && is_array($this->data['messages']))
    {
      $nano = \Nano4\get_instance();
      $nano->sess->messages = $this->data['messages'];
    }
  }

  // Redirect to another page, and show a message.
  public function redirect_msg ($name, $url=Null, $opts=array())
  {
    $opts['session'] = True;
    $this->message($name, $opts);
    $this->redirect($url, $opts);
  }

  // Go to another page, and show a message.
  public function go_msg ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['session'] = True;
    $this->message($msg, $mopts);
    $this->go($page, $params, $gopts);
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

  // Use this when you want to redirect to another page, and show the error.
  public function redirect_warn ($name, $url=Null, $opts=array())
  {
    $opts['type']  = 'warning';
    $this->redirect_msg($name, $url, $opts);
  }

  // Go to another page, showing an error.
  public function go_error ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['type'] = 'error';
    $this->go_msg($msg, $page, $params, $gopts, $mopts);
  }

  // Go to another page, showing a warning.
  public function go_warn ($msg, $page, $params=[], $gopts=[], $mopts=[])
  {
    $mopts['type'] = 'warning';
    $this->go_msg($msg, $page, $params, $gopts, $mopts);
  }

  // Add a warning to the stack.
  public function warning ($name, $opts=[])
  {
    $opts['type']  = 'warning';
    $this->message($name, $opts);
  }

  // An alias for warning()
  public function warn ($name, $opts=[])
  {
    return $this->warning($name, $opts);
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

  /**
   * Add a 'status_messages' JSON element.
   * Pass it a list of messages to include in the JSON.
   */
  public function add_status_json ($messages)
  {
    $this->add_json('status_messages', $this->text->strArray($messages));
  }

}

