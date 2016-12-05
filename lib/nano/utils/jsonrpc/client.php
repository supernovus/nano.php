<?php

namespace Nano\Utils\JSONRPC;

use Nano\Utils\JSONRPC\Client\Transport;

const JSONRPC_ID_RAND = 0;
const JSONRPC_ID_TIME = 1;
const JSONRPC_ID_UUID = 2;
const JSONRPC_ID_UNIQ = 3;

function EXCEPT ($message)
{
  throw new \Exception("# JSON-RPC: $message");
}

function DEBUG ($message)
{
  if (!is_string($message))
  {
    $message = json_encode($message);
  }
  error_log("# JSON-RPC: $message");
}

/**
 * A simple JSON-RPC client.
 */
class Client
{
  public $version = 1;     // Use 2 for JSON-RPC 2.0
  public $debug   = false; // Enable debugging.
  public $notify  = false; // Force notification mode, use with care.
  public $batch   = false; // If true, use batch procesing (2.0 only.)

  public $idtype  = JSONRPC_ID_TIME; // The generation method for request id.
  public $batch_name = 'send';       // Default method name for sending batch.

  public $notifications = []; // A list of methods that are notifications.
  public $named_params  = []; // Any methods that use named params (2.0 only.)

  protected $batch_requests = []; // Any requests in the batch queue.
  protected $transport;           // The transport object.

  /**
   * Build a JSON-RPC client.
   *
   * @param Array $opts   Named parameters.
   *
   *  'debug'         If true, we enable debugging.
   *  'idtype'        One of JSONRPC_ID_RAND, JSONRPC_ID_TIME, JSONRPC_ID_UUID.
   *  'version'       Either 1 (default) or 2 (for JSON-RPC 2.0).
   *  'batch'         If true, we use batch mode (2.0 only)
   *  'batch_name'    Override the method name used to send batch requests.
   *                  We use 'send' by default. (Only if batch is true.)
   *  'notifications' An array of methods that are notifications.
   *  'named_params'  An array of methods that use named params (2.0 only.)
   *  'transport'     One of the transport classes:
   *                  'http'    A simple HTTP stream class (default).
   *                  'curl'    The Nano Curl library is used for transport.
   *                  'socket'  The Nano Socket library is used for transport.
   *
   * Any extra parameters are sent to the Transport object's constructor.
   * The default 'http' transport REQUIRES the 'url' parameter to specify
   * the JSON-RPC URL endpoint.
   *
   * Any of the parameters other than 'transport' can be changed after
   * initialization using object properties of the same name.
   */
  public function __construct (Array $opts)
  { // Handle scalar options.
    foreach (['debug','idtype','version','batch','batch_name'] as $opt)
    {
      if (isset($opts[$opt]) && is_scalar($opts[$opt]))
      {
        $this->$opt = $opts[$opt];
      }
    }
    // Handle array options.
    foreach (['notifications','handle_errors','named_params'] as $opt)
    {
      if (isset($opts[$opt]) && is_array($opts[$opt]))
      {
        $this->$opt = $opts[$opt];
      }
    }

    // Handle our transport.
    $trans = isset($opts['transport']) ? $opts['transport'] : 'http';
    if (is_string($trans))
    {
      if (strpos($trans, "\\") === false)
        $trans = "\\Nano\\Utils\\JSONRPC\\Client\\$trans";
      $opts['jsonrpc_client'] = $this;
      $this->transport = new $trans($opts);
    }
    elseif (is_object($trans) && $trans instanceof Transport)
    {
      $this->transport = $trans;
    }
  }

  /**
   * Builds requests based on object method calls.
   */
  public function __call ($method, $params)
  {
    if (!is_scalar($method))
    {
      EXCEPT("Method name has no scalar value.");
    }

    if (!is_array($params))
    {
      EXCEPT("Parameters must be an array.");
    }

    if ($this->version == 2 && $this->batch && $method == $this->batch_name)
    {
      return $this->_rpc_send_batch_requests();
    }

    $callback = null;
    if ($this->version == 2 && !$this->notify && $this->batch && is_callable($params[0]))
    { // A callback.
      $callback = array_shift($params);
    }

    if ($this->version == 2 && in_array($method, $this->named_params))
    {
      if (is_array($params[0]))
      {
        $params = $params[0];
      }
      else
      {
        EXCEPT("Named parameters expected in '$method'.");
      }
    }

    $request = [];
    if ($this->version == 2)
    {
      $request['jsonrpc'] = '2.0';
    }
    $request['method'] = $method;
    $request['params'] = $params;

    // Determine if we are sending a notification or a method call.
    if ($this->notify || in_array($method, $this->notifications))
    { // We're sending a notification.
      $notify = true;
      if ($this->version == 1)
      { // version 1 is null id, version 2 is absent id. 
        $request['id'] = null;
      }
    }
    else
    { // We're sending a method call, and need to generate an id.
      $notify = false;
      if ($this->idtype == JSONRPC_ID_RAND)
      {
        $id = rand();
      }
      elseif ($this->idtype == JSONRPC_ID_TIME)
      {
        $id = str_replace('.', '', microtime(true));
      }
      elseif ($this->idtype == JSONRPC_ID_UNIQ)
      {
        $id = uniqid();
      }
      elseif ($this->idtype == JSONRPC_ID_UUID)
      {
        $id = \Nano\Utils\UUID::v4();
      }
      else
      {
        EXCEPT("Invalid idtype set.");
      }
      $request['id'] = $id;
    }

    $this->debug && DEBUG(['request'=>$request]);

    if ($this->version == 2 && $this->batch)
    {
      $request_spec = ['request'=>$request];
      if (isset($callback)) $request_spec['callback'] = $callback;
      $this->batch_requests[] = $request_spec;
    }
    else
    {
      return $this->_rpc_send_request($request);
    }
  }

  protected function _rpc_send_request ($request)
  {
    $request_json = json_encode($request);
    $response_text = $this->transport->send_request($request_json);
    $this->debug && DEBUG(["response_text"=>$response_text]);
    if (isset($request['id']))
    { // We're not a notification, let's parse the response.
      $response_json = json_decode($response_text, true);
      $response = new ClientResponse($this, $response_json);
      return $response;
    }
  }

  protected function _rpc_send_batch_requests ()
  {
    $callbacks = [];
    $requests  = [];
    foreach ($this->batch_requests as $request_spec)
    {
      $requests[] = $req = $request_spec['request'];
      if (isset($req['id']) && isset($request_spec['callback']))
      {
        $callbacks[$req['id']] = $request_spec['callback'];
      }
    }
    $request_json = json_encode($requests);
    $response_text = $this->transport->send_request($request_json);
    $this->debug && DEBUG(["response_text"=>$response_text]);
    if (trim($response_text) == '') return; // Empty response.
    $response_json = json_decode($response_text, true);
    if (isset($response_json) && is_array($response_json))
    {
      $responses = [];
      if (isset($response_json['jsonrpc']))
      { // A single response was returned, most likely an error.
        $responses[] = new ClientResponse($this, $response_json);
      }
      elseif (isset($response_json[0]))
      { // An array of responses was received.
        foreach ($response_json as $response)
        {
          $responses[] = $response = new ClientResponse($this, $response);
          if (isset($response->id) && isset($callbacks[$response->id]))
          { // Send the response to the callback.
            $callbacks[$response->id]($response);
          }
        }
      }
      else
      {
        EXCEPT("Batch response was in unrecognized format.");
      }
      return $responses;
    }
  }

}

/**
 * A response from a JSON-RPC server.
 *
 * @property 
 */
class ClientResponse
{
  /**
   * If the response was successful, this will be true.
   * If not, this will be false.
   */
  public $success = false;

  /**
   * The id of the response.
   */
  public $id;

  /**
   * If the response is successful, this will be the response data.
   */
  public $result;

  /**
   * If the response is not successful, this will contain:
   * 
   *  JSON-RPC 1.0  The 'error' property if it is an integer, or null.
   *  JSON-RPC 2.0  The 'code' property of the error object.
   */
  public $code;

  /**
   * If the response is not successful, this will contain:
   *
   *  JSON-RPC 1.0  The 'error' property if it is a string, or null.
   *  JSON-RPC 2.0  The 'message' property of the error object.
   */
  public $message;

  /**
   * If the response is not successful, this will contain:
   *
   *  JSON-RPC 1.0  The 'error' property, if it was not a string or integer.
   *  JSON-RPC 2.0  The 'data' property of the error object, if it was set.
   */
  public $error_data;

  protected $client; // The parent client instance.

  /**
   * Create a ClientResponse object.
   *
   * @param Client $client  The parent Client instance.
   * @param Array $response The response from the server.
   */
  public function __construct (Client $client, $response)
  {
    $this->client = $client;

    if (isset($response) && is_array($response))
    {
      $ver = $this->client->version;
      if ($ver == 2)
      {
        if (!isset($response['jsonrpc']) || $response['jsonrpc'] != '2.0')
        {
          EXCEPT("Response was not in JSON-RPC 2.0 format.");
        }
      }
      if (array_key_exists('id', $response))
      {
        $this->id = $response['id'];
      }
      else
      {
        EXCEPT("Response did not have 'id'");
      }
      if (isset($response['error']))
      {
        if ($ver == 2)
        { // JSON-RPC 2.0 has a very specific error object format.
          if (is_array($response['error']) 
            && isset($response['error']['code'])
            && isset($response['error']['message']))
          {
            $this->code    = $response['error']['code'];
            $this->message = $response['error']['message'];
            if (isset($response['error']['data']))
            {
              $this->error_data = $response['error']['data'];
            }
          }
          else
          {
            EXCEPT("Invalid error object in response.");
          }
        }
        else
        { // JSON-RPC 1.0 has no defined error format, so we guess.
          if (is_array($response['error']) && isset($response['error']['code']))
          { // Using error objects in version 1.0.
            $this->code = $response['error']['code'];
            if (isset($response['error']['message']))
              $this->message = $response['error']['message'];
            if (isset($response['error']['data']))
              $this->error_data = $response['error']['data'];
          }
          if (is_int($response['error']))
          { // A status code.
            $this->code = $response['error'];
          }
          elseif (is_string($response['error']))
          { // An error message.
            $this->message = $response['error'];
          }
          else
          { // Something else.
            $this->error_data = $response['error'];
          }
        }
      }
      elseif (isset($response['result']))
      {
        $this->result = $response['result'];
        $this->success = true;
      }
    }
    else
    {
      EXCEPT("Invalid response in ClientResponse()");
    }
  }
}