<?php

namespace Nano\Utils\Currency;

/**
 * Get exchange rates and conversions using the OpenExchangeRates API.
 */

class OpenExchangeRates
{
  protected $base;
  protected $rates;

  public function __construct ($config)
  {
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
