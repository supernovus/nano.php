<?php

namespace Nano\Utils;

/**
 * New unified Notifications library.
 *
 * Can be used for a completely Javascript powered notification system using
 * the Nano.Notifications library from Nano.js, or for a purely PHP rendered
 * notifications system. Using the Javascript version is recommended.
 */

class Notifications
{
  public $text;

  protected $status_types =
  [
    'default' => ['class'=>'message', 'prefix'=>'msg.'],
    'error'   => ['class'=>'error',   'prefix'=>'err.'],
    'warning' => ['class'=>'warning', 'prefix'=>'warn.'],
    'notice'  => ['class'=>'notice',  'noGroup'=>true],
  ];

  protected $messages = [];
  protected $has_status_type = [];
  protected $has_status_key  = [];

  public $reparent = false;

  /**
   * Get the 'notifications' instance from the PHP Session if it exists,
   * or create a new Notifications object if it doesn't exist.
   *
   * @param array $opts Options to pass to constructor.
   */
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

  /**
   * Create a new Notifications instance.
   *
   * @param array $opts  Options:
   *
   *   'text'  The Translations instance to use to get message text.
   *   'status_types'  A map of name=>def to pass to addStatusType().
   *   
   */
  public function __construct ($opts=[])
  {
    if (isset($opts['text']))
    {
      $this->text = $opts['text'];
    }

    if (isset($opts['status_types']) && is_array($opts['status_types']))
    {
      foreach ($opts['status_types'] as $name => $def)
      {
        $this->addStatusType($name, $def);
      }
    }
  }

  /**
   * Add a custom status type.
   *
   * @param string $name The name of the status type.
   * @param array  $def  The status type definition.
   */
  public function addStatusType ($name, $def)
  {
    if (!is_string($name)) throw new \Exception("addStatusType 'name' must be a string.");
    if (!is_array($def)) throw new \Exception("addStatusType 'def' must be an associative array.");
    $this->status_types[$name] = $def;
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
      unset($opts['type']);
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
   * Get message id
   */
  public function getMsgId ()
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

    return $prefix.$name.$suffix;
  }

  /**
   * Get the rendered message text.
   */
  public function getText ()
  {
    $opts = $this->opts;
    $msgid = $this->getMsgId();

    $text = $this->parent->text->getStr($msgid, $opts);

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
