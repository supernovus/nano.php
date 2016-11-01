<?php

namespace Nano\Utils\Currency;

/**
 * Use Google to get currency exchange rates.
 */

class Google
{
  function convert ($amount, $from, $to)
  {
    $params = $amount.$from."=?".$to;
    $url    = "http://www.google.com/ig/calculator?hl=en&q=".$params;

    $opts =
    [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_CONNECTTIMEOUT => 5,
    ];
    $curl = curl_init($url);
    curl_setopt_array($curl, $opts);
    $response = curl_exec($curl);
    curl_close($curl);
    
    $response = str_replace(chr(160), '', $response);
#    error_log("response> $response");
    $response = preg_replace("/(\w+):/", '"$1":', $response);
    $response = json_decode($response, true);

    $rhs = preg_split("/\s+/", $response['rhs']);
    
    $value = '';

    foreach ($rhs as $bit)
    {
#      error_log("bit> '$bit'");
      if (is_numeric($bit))
      {
        $value .= $bit;
      }
      elseif ($bit == 'million')
      {
        $value *= 1000000;
      }
      elseif ($bit == 'billion')
      {
        $value *= 1000000000;
      }
      elseif ($bit == 'trillion')
      {
        $value *= 1000000000000;
      }
    }
    $value = (float)$value;
    $rate = $value / $amount;
    $return =
    [
      "value" => $value,
      "rate"  => $rate,
    ];
    return $return;
  }
}
