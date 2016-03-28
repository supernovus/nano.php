<?php

namespace Nano4\Data;

trait JSON
{
  abstract public function to_array($opts=[]);

  /**
   * Convert to a JSON string, optionally with fancy formatting.
   */
  public function to_json ($opts=[])
  {
    if (is_bool($opts))
    {
      $opts = ['fancy'=>$opts];
    }
    $flags = isset($opts['jsonFlags']) ? $opts['jsonFlags'] : 0;
    $optmap =
    [
      'hexTag'       => JSON_HEX_TAG,
      'hexAmp'       => JSON_HEX_AMP,
      'hexApos'      => JSON_HEX_APOS,
      'hexQuot'      => JSON_HEX_QUOT,
      'forceObject'  => JSON_FORCE_OBJECT,
      'bigintStr'    => JSON_BIGINT_AS_STRING,
      'fancy'        => JSON_PRETTY_PRINT,
      'slashes'      => JSON_UNESCAPED_SLASHES,
      'unicode'      => JSON_UNESCAPED_UNICODE,
      'partial'      => JSON_PARTIAL_OUTPUT_ON_ERROR,
      'float'        => JSON_PRESERVE_ZERO_FRACTION,
    ];
    foreach ($optmap as $optname => $flag)
    {
      if (isset($opts[$optname]) && $opts[$optname])
      {
        $flags = $flags | $flag;
      }
    }
    $array = $this->to_array($opts);
    return json_encode($array, $flags);
  }

}
