<?php

namespace Nano\Utils\JSONRPC\Client;

/**
 * Curl transport.
 */

class Curl implements Transport
{
  protected $client;

  public $url;
  public $curl;

  public function __construct (Array $opts)
  {
    if (isset($opts['jsonrpc_client']))
    {
      $this->client = $opts['jsonrpc_client'];
    }
    if (isset($opts['url']))
    {
      $this->url = $opts['url'];
    }
    $curlopts = isset($opts['curl']) ? $opts['curl'] : [];
    $this->curl = new \Nano\Utils\Curl($curlopts);
    $this->curl->content_type('application/json');
  }

  public function send_request ($request)
  {
    if (!isset($this->url))
    {
      throw new \Exception("JSON-RPC: No url specified for HTTP transport.");
    }

    $response = $this->curl->post($this->url, $request, true);

    if (is_array($response))
    {
      $msg = json_encode($response);
      throw new \Exception("JSON-RPC: Curl returned error: $msg");
    }
    else
    {
      return $response;
    }
  }
}
