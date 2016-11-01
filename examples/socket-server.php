<?php

require_once 'lib/nano/init.php';

\Nano\register();

$s = new \Nano\Utils\SocketDaemon(array('port'=>2525));
$_server_listening = true;

while ($_server_listening)
{ $c = $s->accept();
  if ($c === false)
    usleep(100);
  elseif ($c->socket > 0)
  { $_client_listening = true;
    do
    { $command = trim($c->read(1024));
      switch ($command) {
        case 'QUIT':
          $c->write("Shutting down\n");
          $c->close();
          $s->close(true,true);
          $_client_listening = false;
          $_server_listening = false;
          break;
        case 'END':
          $c->write("Goodbye\n");
          $c->close();
          $_client_listening = false;
          break;
        default:
          $c->write("ECHO $command\n");
      }
    } while ($_client_listening);
    echo "Client exited\n";
  }
  else
  { echo "error: ".$c->error_msg()."\n";
    die;
  }
}
echo "Server shutdown\n";

