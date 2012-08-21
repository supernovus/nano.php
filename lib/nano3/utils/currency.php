<?php

namespace Nano3\Utils;

/**
 * Currency database, and formatting.
 */

class Currency
{
  /**
   * The database of known currencies.
   * An associative array, where the key is the ISO symbol of the currency,
   * and the value is an associative array with at least a 'sign' and 'pos' key.
   * The 'sign' key marks the symbol to use for the currency, and the 'pos' can
   * be one of 'left', 'right' or 'infix'.
   */
  protected $currencies;

  /**
   * The default currency to use, if none is specified in the translate() method.
   */
  public $currency;

  /**
   * Encode the UTF-8 characters into Numeric Character Reference format? Default: False
   */
  public $encode = False;

  /**
   * Build a new Currency object.
   *
   * @param array $currencies   The currency database.
   * @param array $opts         Optional parameters, see below.
   *
   *   'currency'       The default currency to use, if none is specified.
   *   'encode'         Whether we should encode UTF-8 into NCR.
   *
   */
  public function __construct ($currencies, $opts=array())
  {
    $this->currencies = $currencies;
    if (isset($opts['currency']))
    {
      $this->currency = $opts['currency'];
    }
    if (isset($opts['encode']))
    {
      $this->encode = $opts['encode'];
    }
  }

  /**
   * Format a number into a currency string.
   */
  public function format ($value, $name=Null)
  {
    if (!isset($name))
    {
      if (isset($this->currency))
      {
        $name = $this->currency;
      }
      else
      {
        throw new \Exception("No currency specified");
      }
    }
    if (isset($this->currencies[$name]))
    {
      $currency = $this->currencies[$name];
    }
    else
    {
      throw new \Exception("Invalid currency specified");
    }
    $sign = $currency['sign'];
    if ($this->encode)
    {
      $uro = new UTF8XML();
      $sign = $uro->encode($sign);
    }
    $pos = strtolower($currency['pos']);
    if ($pos == 'left')
    {
      $label = $sign . $value;
    }
    elseif ($pos == 'right')
    {
      $label = "$value $sign";
    }
    elseif ($pos == 'infix')
    {
      if (preg_match('/\./', $value))
        $label = str_replace('.', $sign, $value);
      else
        $label = $value . $sign;
    }
    else
    { // Unrecognized position.
      $label = $value;
    }
    return $label;
  } // end of function format()

} // end of class Currency
