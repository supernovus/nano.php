<?php

namespace Nano4\Utils;

class CSV
{ /** 
   * A CSV parser with more options than anything PHP has built in.
   * Unlike PHP's various getcsv functions, this handles embedded newlines,
   * and "" characters properly. Inspired by some of the examples on:
   * http://php.net/manual/en/function.str-getcsv.php 
   */
  public static function parse ($str, $opts=[])
  {
    if (is_string($opts))
    {
      $opts = ['delimiter'=>$opts];
    }
    elseif (!is_array($opts))
    {
      throw new \Exception("Invalid options passed to CSV::parse()");
    }

    $delimiter = isset($opts['delimiter']) ? $opts['delimiter'] : "\t";
    $useregex  = isset($opts['regex'])     ? $opts['regex']     : false;
    $trimcols  = isset($opts['trim'])      ? $opts['trim']      : false;
    $assoc     = isset($opts['assoc'])     ? $opts['assoc']     : false;
    $escape    = isset($opts['escape'])    ? $opts['escape']    : "\0";

    if (is_string($useregex))
    {
      $delimiter = $useregex;
      $useregex  = true;
    }

    $str = preg_replace_callback('/([^"]*)("((""|[^"])*)"|$)/s', 
      function ($matches) use ($escape, $delimiter)
      {
        if (count($matches) > 3)
        {
          $str = str_replace("\r", $escape.'R', $matches[3]);
          $str = str_replace("\n", $escape.'N', $str);
          $str = str_replace('""', $escape.'Q', $str);
          $str = str_replace($delimiter, $escape.'D', $str);
          return $matches[1] . $str;
        }
        else
        {
          return $matches[1];
        }
      }, 
      $str
    );

    $str = preg_replace('/\n$/', '', $str); // Remove last newline.

    $lines = explode("\n", $str);

    $rows = array_map(function ($line) 
      use ($delimiter, $trimcols, $escape, $useregex)
    {
      if ($useregex)
      {
        $fields = preg_split($delimiter, $line);
      }
      else
      {
        $fields = explode($delimiter, $line);
      }
      return array_map(function ($field) use ($delimiter, $escape, $trimcols)
      {
        $field = str_replace($escape.'D', $delimiter, $field);
        $field = str_replace($escape.'Q', '"',  $field);
        $field = str_replace($escape.'N', "\n", $field);
        $field = str_replace($escape.'R', "\r", $field);
        if ($trimcols) $line = trim($field);
        return $field;
      },
      $fields);
    }, $lines);

    if ($assoc)
    { // Generate an associative array. The first row is the headers.
      $header = null;
      $assoc  = [];
      foreach ($rows as $row)
      {
        if (!isset($header))
        {
          $header = $row;
        }
        else
        {
          $assoc[] = array_combine($header, $row);
        }
      }
      return $assoc;
    }
    else
    { // Return the flat rows, including the header.
      return $rows;
    }
  } // end of parse ()

}
