<?php

namespace Nano\Utils;

/**
 * New unified Notifications library.
 *
 * Can be used for a completely Javascript powered notification system using
 * the Nano.Notifications library from Nano.js, or for a purely PHP rendered
 * notifications system.
 */

class Notifications
{
  public $text;

  protected $status_types =
  [
    'default' => ['class'=>'message', 'prefix'=>'msg.'],
    'error'   => ['class'=>'error',   'prefix'=>'err.'],
    'warning' => ['class'=>'warning', 'prefix'=>'warn.'],
  ];

  protected $messages = [];
  protected $has_status_type = [];
  protected $has_status_key  = [];

  public $reparent = false;

  public static function getInstance ($opts=[])
  {
    $nano = \Nano\get_instance();
    $sess = $nano->sess;
    if (isset($sess->notifications))
    {
      $instance = $sess->notifications;
      if ($instance->reparent)
      {
        if (isset($opts['text']))
          $instance->text = $opts['text'];
      }
      unset($sess->notifications); // Remove it from the session.
      return $instance;
    }
    else
    {
      return new Notifications($opts);
    }
  }

  public function __construct ($opts=[])
  {
    if (isset($opts['text']))
      $this->text = $opts['text'];
  }

  public function store ()
  {
    if (count($this->messages) > 0)
    {
      $nano = \Nano\get_instance();
      $sess = $nano->sess;
      $sess->notifications = $this;
    }
  }

  public function addMessage ($name, $opts=[])
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
    unset($opts['class']);

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

    $key = preg_replace('/\s+/', '_', $name);

    if (isset($opts['session']))
    {
      $useSession = $opts['session'];
      unset($opts['session']);
    }
    else
    {
      $useSession = false;
    }

    $message = ['key'=>$key, 'name'=>$name, 'class'=>$class, 'opts'=>$opts];
    $message = new Notification(['parent'=>$this, 'data'=>$message]);

    $this->messages[] = $message;
    $this->has_status_type[$class] = true;
    if (isset($this->has_status_key[$key]))
      $this->has_status_key[$key]++;
    else
      $this->has_status_key[$key] = 1;
    
    if ($useSession)
    {
      $this->store();
    }
  }

  public function getMessages ()
  {
    return $this->messages;
  }

  public function hasStatus ($type)
  {
    if (isset($this->has_status_type[$type]))
    {
      return $this->has_status_type[$type];
    }
    return false;
  }

  public function keyCount ($key)
  {
    if (isset($this->has_status_key[$key]))
    {
      return $this->has_status_key[$key];
    }
    return 0;
  }

  public function msgCount ()
  {
    return count($this->messages);
  }
}

class Notification extends \Nano\Data\Arrayish
{
  protected $newconst = true;

  /**
   * Get the rendered message text.
   */
  public function getText ()
  {
    $name = $this->name;
    $opts = $this->opts;

    if (!is_numeric(strpos(':', $name)) && isset($opts['prefix']))
    {
      $prefix = $opts['prefix'];
    }
    else
    {
      $prefix = '';
    }

    if (isset($opts['suffix']))
    {
      $suffix = $opts['suffix'];
    }
    else
    {
      $suffix = '';
    }

    $text = $this->parent->text->getStr($prefix.$name.$suffix, $opts);

    if (isset($opts['prefix']))
    {
      $text = str_replace($opts['prefix'], '', $text);
    }
    if (isset($opts['suffix']))
    {
      $text = str_replace($opts['suffix'], '', $text);
    }

    return $text;
  }

  /**
   * Get the key count.
   */
  public function keyCount ()
  {
    if (isset($this->key))
    {
      return $this->parent->keyCount($this->key);
    }
    return 1;
  }
}
