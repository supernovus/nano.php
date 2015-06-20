<?php

namespace JSONRPCTest;

require_once 'lib/nano4/init.php';
require_once 'lib/test.php';

\Nano4\register();

use Nano4\Utils\JSONRPC;
use function Nano4\Utils\JSONRPC\get_named_param; // In JSONRPC\Server

plan(18);

$DEBUG = false;

class TestServer implements JSONRPC\Client\Transport
{
  use JSONRPC\Server;

  public $use_v1_errors = false;
  public $use_v1_named  = false;

  protected $sessions = [];

  public function send_request ($request)
  {
    $opts = [];
    if ($this->use_v1_errors)
      $opts['v1errors'] = true;
    if ($this->use_v1_named)
      $opts['v1named'] = true;
    return $this->handle_jsonrpc_request($request, $opts);
  }

  public function start_session ()
  {
    $sid = uniqid();
    return $this->sessions[$sid] = ['sid'=>$sid, 'started'=>microtime(true)];
  }

  protected function validate_session ($sid)
  {
    if (!isset($sid))
      throw new JSONRPC\InvalidParams();
    if (!isset($this->sessions[$sid]))
      throw new InvalidSID();
  }

  public function get_session_data ($sid, $key=null)
  {
    $key = get_named_param($sid, 'key', $key);
    $sid = get_named_param($sid, 'sid', $sid);
    $this->validate_session($sid);
    if (isset($key))
    {
      if (isset($this->sessions[$sid][$key]))
      {
        return $this->sessions[$sid][$key];
      }
      else
      {
        throw new InvalidKey();
      }
    }
    return $this->sessions[$sid];
  }

  public function set_session_data ($sid, $key=null, $val=null)
  {
    $key = get_named_param($sid, 'key',   $key);
    $val = get_named_param($sid, 'value', $val);
    $sid = get_named_param($sid, 'sid',   $sid);
    $this->validate_session($sid);
    if (!isset($key, $val))
      throw new JSONRPC\InvalidParams();
    $this->sessions[$sid][$key] = $val;
    return true;
  }

  public function keepalive ($sid)
  {
    $sid = get_named_param($sid, 'sid', $sid);
    $this->validate_session($sid);
    if (isset($this->sessions[$sid]['keepalive']))
    {
      $this->sessions[$sid]['keepalive'] += 1;
    }
    else
    {
      $this->sessions[$sid]['keepalive'] = 1;
    }
  }

  public function end_session ($sid)
  {
    $data = $this->get_session_data($sid);
    $data['finished'] = microtime(true);
    return $data;
  }

}

class InvalidSID extends JSONRPC\Exception
{
  protected $code    = 1000;
  protected $message = 'Invalid session id';
}

class InvalidKey extends JSONRPC\Exception
{
  protected $code    = 1001;
  protected $message = 'Invalid key';
}

function jerr ($r, $n="response")
{
  error_log("# $n: ".json_encode($r));
}

$server = new TestServer();

$client = new JSONRPC\Client(['transport'=>$server, 'debug'=>$DEBUG]);
$client->notifications[] = 'keepalive';

$response = $client->start_session();

if ($DEBUG)
  jerr($response);

ok($response->success, "start_session() returned ok");

ok(isset($response->result, $response->result['sid']), "sid was set");

$sid = $response->result['sid'];

ok (isset($response->result, $response->result['started']), "started was set");

$started = $response->result['started'];

$response = $client->keepalive($sid); // keepalive = 1

is($response, null, "keepalive returns null");

$response = $client->set_session_data($sid, 'hello', 'world');

if ($DEBUG)
  jerr($response);

ok($response->success, "set_session_data() returned ok");

is($response->result, true, "set_session_data() returned true");

$client->keepalive($sid); // keepalive = 2

$response = $client->get_session_data($sid, 'hello');

ok($response->success, "get_session_data(sid, key) returned ok");

is($response->result, 'world', "get_session_data(sid, key) returned proper data");

$client->keepalive($sid); // keepalive = 3

$response = $client->get_session_data($sid);

if ($DEBUG)
  jerr($response);

ok($response->success, "get_session_data(sid) returned ok");

ok(isset($response->result, $response->result['keepalive']), "get_session_data(sid) returned proper data");

is($response->result['keepalive'], 3, 'keepalive is proper value');

$response = $client->set_session_data($sid);

if ($DEBUG)
  jerr($response);

ok(!$response->success, "missing parameters returns not ok");

is ($response->message, 'Invalid params', 'correct error message');

$response = $client->set_session_data('foo', 'hello', 'world');

ok (!$response->success, "invalid parameter value returns not ok");

is ($response->message, 'Invalid session id', 'correct error message');

$response = $client->end_session($sid);

ok ($response->success, "end_session() returned ok");

ok (isset($response->result, $response->result['finished']), 'finished was set');

$finished = $response->result['finished'];

ok(($finished > $started), 'finished is greater than started');

// TODO: test version 2.0 calls and named parameters.

