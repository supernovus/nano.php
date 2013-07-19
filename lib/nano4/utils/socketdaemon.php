<?php

namespace Nano4\Utils;

/**
 * Socket Daemon
 *
 */

class SocketDaemon extends Socket {
  public function __construct ($opts=array(), $start=true) 
  { parent::__construct($opts, false);
    if (isset($opts['socket']))
      return $this;
    if ($start)
    { $this->try_call($this->bind());
      $this->try_call($this->listen());
      if (isset($opts['nonblock']))
        $this->try_call($this->nonblock());
    }
  }

  public function nonblock ()
  { return socket_set_nonblock($this->socket);
  }

  public function block ()
  { return socket_set_block($this->socket);
  }

  public function bind ($address=NULL, $port=NULL)
  { $this->set_addr($address, $port);
    return socket_bind($this->socket, $this->address, $this->port);
  }

  public function listen ($backlog=0)
  { return socket_listen($this->socket, $backlog);
  }

  public function accept ($object=true)
  { $socket = socket_accept($this->socket);
    if ($object && $socket)
      return new SocketDaemon(array('socket'=>$socket));
    return $socket;
  }

}

