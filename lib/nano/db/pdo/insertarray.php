<?php

namespace Nano\DB\PDO;

class InsertArray
{
  public $fields;
  public $values;

  protected $allow_pk = false;

  public function __construct ($data, $opts=[])
  {
    if (isset($opts['allowpk']))
      $this->allow_pk = true;
    $insert = $this->buildInsert($data, $this->allow_pk);
    if (isset($insert) && count($insert) == 2)
    {
      $this->fields = $insert[0];
      $this->values = $insert[1];
    }
    else
    {
      throw new \Exception("Could not build InsertArray, sorry.");
    }
  }

  public static function buildInsert ($indata, $allowpk)
  {
    $fieldnames = '';
    $fieldvals  = '';
    $fielddata  = array();
    $keys = array_keys($row);
    $kc   = count($row);
    for ($i=0; $i < $kc; $i++)
    {
      $key = $keys[$i];
      if ($key == $pk && !$allowpk) continue; // Skip primary key.
      $fieldnames .= $key;
      $fieldvals  .= ':'.$key;
      $fielddata[$key] = $row[$key];
      if ($i != $kc - 1)
      {
        $fieldnames .= ', ';
        $fieldvals  .= ', ';
      }
    }
    return [$fieldnames, $fieldvals];
  }

}