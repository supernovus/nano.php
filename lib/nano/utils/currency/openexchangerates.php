<?php

namespace Nano\Utils\Currency;

/**
 * Get exchange rates and conversions using the OpenExchangeRates API.
 */
class OpenExchangeRates
{
  protected $base;
  protected $rates;

  public static function getJson ($appId, $decoded=false)
  {
    if (!isset($appId))
    {
      throw new \Exception("Missing OpenExchangeRates AppId");
    }
    $curl = new \Nano\Utils\Curl();
    $curl->strict = true;
    $url  = "https://openexchangerates.org/api/latest.json?app_id=$appId";
    $text = $curl->get($url);
    if ($decoded)
    {
      $json = json_decode($text, true);
      if (!is_array($json))
      {
        throw new \Exception("Invalid response from OpenExchangeRates");
      }
      return $json;
    }
    return $text;
  }

  public static function updateConfig ($outputFile, $appId)
  {
    if (!is_writable($outputFile))
    {
      throw new \Exception("Config file '$outputFile' is not writable");
    }
    $configText = self::getJson($appId);
    file_put_contents($outputFile, $configText);
  }

  public function __construct ($config)
  {
    if (is_string($config))
    { // Assume it's the appId, we'll get the rates from the API.
      $config = self::getJson($config, true);
    }
    if (!is_array($config))
    { // Something obviously went wrong somewhere.
      throw new \Exception("OpenExchangeRates must be passed an AppId string or configuraton array");
    }
    if (isset($config['base']) && isset($config['rates']))
    {
      $this->base  = $config['base'];
      $this->rates = $config['rates'];
      if (!isset($this->rates[$this->base]))
      {
        $this->rates[$this->base] = 1;
      }
    }
    else
    {
      throw new \Exception("Invalid OpenExchange config");
    }
  }

  public function getRate ($from, $to)
  {
    if (isset($this->rates[$to]) || isset($this->rates[$from]))
    {
      if ($from == $this->base)
      {
        return $this->rates[$to];
      }
      elseif ($to == $this->base)
      {
        return 1 / $this->rates[$from];
      }
      else
      {
        return $this->rates[$to] * (1 / $this->rates[$from]);
      }
    }
    else
    {
      return 0;
    }
  }

  public function convert ($val, $from, $to)
  {
    return $val * $this->getRate($from, $to);
  }

}
