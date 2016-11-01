<?php

/**
 * Sockets Made Easy
 * An object oriented wrapper for PHP socket programming.
 * Author: Timothy Totten
 */

namespace Nano\Utils;
use Nano\Exception;

class Socket {
  public $socket;
  private $debug;
  protected $address = '127.0.0.1';
  protected $port = 0;

  public function __construct ($opts=array(), $connect=true)
  { $socket = false;
    if (isset($opts['socket']))
    { $this->socket = $opts['socket'];
      return $this;
    }

    // This is barely implemented, but write_str will use it.
    if (isset($opts['debug']))
    { $debugfile = fopen($opts['debug'], "w");
      $this->debug = $debugfile;
    }

    $domain = AF_INET;
    $type   = SOCK_STREAM;
    $proto  = SOL_TCP;
    if (array_key_exists('connect',$opts) && is_array($opts['connect']))
    { $domain = $opts['connect'][0];
      $type   = $opts['connect'][1];
      $proto  = $opts['connect'][2];
    }
    else
    { if (isset($opts['domain']))
      { $domain = $opts['domain'];
        if ($domain == AF_UNIX) $proto = 0;
      }
      if (isset($opts['type']))
        $type = $opts['type'];
      if (isset($opts['proto']))
        $proto = $opts['proto'];
    }

    if (isset($opts['address']))
      $this->address = $opts['address'];
    elseif ($domain == AF_UNIX && isset($opts['path']))
      $this->address = $opts['path'];
    elseif (isset($opts['ip']))
      $this->address = $opts['ip'];

    if (isset($opts['port']))
      $this->port = $opts['port'];

    $this->try_call($this->socket = socket_create($domain, $type, $proto));

    if ($connect)
      $this->try_call($this->connect());

  }

  protected function set_addr($address, $port)
  { if (!is_null($address))
      $this->address = $address;
    if (!is_null($port))
      $this->port = $port;
  }

  public function connect ($address=NULL, $port=NULL)
  { $this->set_addr($address, $port);
    return socket_connect($this->socket, $this->address, $this->port);
  }

  public function shutdown ($what=2)
  { return socket_shutdown($this->socket, $what);
  }

  public function close ($graceful=false, $linger=false)
  { if (isset($this->debug))
      fclose($this->debug);
    if ($linger)
    { $arrOpt = array('l_onoff' => 1, 'l_linger' => 1);
      $this->block();
      socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $arrOpt);
    }
    if ($graceful)
    { $this->shutdown(1);
      usleep(500);
      $this->shutdown(0);
    }
    return socket_close($this->socket);
  }

  public function error_code ()
  { return socket_last_error ($this->socket);
  }

  public function error_msg ()
  { return socket_strerror($this->error_code());
  }

  public function try_call ($try)
  { if (!$try)
    { $msg = $this->error_msg();
      $this->close();
      throw new Exception ($msg);
    }
  }

  public function send ($msg, $len=NULL, $flags=0)
  { if (is_null($len))
      $len = strlen($msg);
    return socket_send($this->socket, $msg, $len, $flags);
  }

  public function send_str ($buf, $flags=0, $fatal=true)
  { $n = 0;
    while ($n < strlen($buf))
    { $t = $this->send(substr($buf, $n), strlen($buf)-$n, $flags);
      if ($fatal) $this->try_call($t);
      $n += $t;
    }
  }

  public function write ($msg, $len=NULL)
  { if (is_null($len))
      $len = strlen($msg);
    if (isset($this->debug))
      fwrite($this->debug, $msg, $len);
    return socket_write($this->socket, $msg, $len);
  }

  public function write_str ($buf, $fatal=true)
  { $n = 0;
    while ($n < strlen($buf))
    { $t = $this->write(substr($buf, $n), strlen($buf)-$n);
      if ($fatal) $this->try_call($t);
      $n += $t;
    }
  }

  public function read ($len, $type=PHP_BINARY_READ)
  { return socket_read($this->socket, $len, $type);
  }

  public function recv (&$buf, $len, $flags=0)
  { return socket_recv($this->socket, $buf, $len, $flags);
  }

}
