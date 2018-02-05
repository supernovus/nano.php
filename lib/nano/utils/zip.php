<?php

namespace Nano\Utils;

class Zip
{
  public static function zip_error ($msg, $code)
  {
    $codes =
    [
      \ZipArchive::ER_EXISTS => 'file exists',
      \ZipArchive::ER_INCONS => 'archive inconsistency',
      \ZipArchive::ER_INVAL  => 'invalid argument',
      \ZipArchive::ER_MEMORY => 'malloc failure',
      \ZipArchive::ER_NOENT  => 'no such file',
      \ZipArchive::ER_NOZIP  => 'not a zip archive',
      \ZipArchive::ER_OPEN   => 'could not open file',
      \ZipArchive::ER_READ   => 'error reading file',
      \ZipArchive::ER_SEEK   => 'seek error',
    ];
    if (isset($codes[$code]))
      $msg .= ': ' . $codes[$code];
    throw new \Exception($msg);
  }

  public static function create ($filename)
  {
    $zip = new \ZipArchive();
    $res = $zip->open($filename, \ZipArchive::CREATE);
    if ($res !== true)
    {
      static::zip_error("Could not create zip", $res);
    }
    return $zip;
  }

  public static function open ($filename)
  {
    $zip = new \ZipArchive();
    $res = $zip->open($filename);
    if ($res != true)
    {
      static::zip_error("Could not open zip", $res);
    }
    return $zip;
  }
}