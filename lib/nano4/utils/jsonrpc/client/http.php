<?php

namespace Nano4\Utils\JSONRPC\Client;

/**
 * Simple HTTP transport.
 */

class HTTP implements Transport
{
  protected $client;
 
  public $url;

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
  }

  public function send_request ($request)
  {
    if (!isset($this->url))
    {
      throw new \Exception("JSON-RPC: No url specified for HTTP transport.");
    }

    $ctxopts = 
    [
      'http' =>
      [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => $request,
      ],
    ];

    $context = stream_context_create($ctxopts);

    if ($fp = fopen($this->url, 'r', false, $context))
    {
      $response = '';
      while ($line = fgets($fp))
      {
        $response .= trim($line)."\n";
      }
      fclose($fp);
      return $response;
    }
    else
    {
      throw new \Exception("JSON-RPC: Could not connect to ".$this->url);
    }
  }
}
