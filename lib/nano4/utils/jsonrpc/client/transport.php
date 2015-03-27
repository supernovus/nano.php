<?php

namespace Nano4\Utils\JSONRPC\Client;

/**
 * Interface for Transport libraries.
 */

interface Transport
{
  /**
   * Build the Transport object.
   */
  public function __construct (\Nano4\Utils\JSONRPC\Client $client, Array $opts);

  /**
   * Send the request to the desired transport.
   */
  public function send_request ($request);

}
