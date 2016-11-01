<?php

namespace Nano\Utils\JSONRPC\Client;

/**
 * Interface for Transport libraries.
 */

interface Transport
{
  /**
   * Send the request to the desired transport.
   */
  public function send_request ($request);

}
