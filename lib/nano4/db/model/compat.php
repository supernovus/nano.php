<?php

namespace Nano4\DB\Model;

/**
 * Compatibility wrapper for the old DB\Model methods.
 *
 * We will eventually deprecate this, in which case we will add
 * warning messages that will be displayed in the error logs, so we can
 * track down any code using these old methods easier.
 */
trait Compat
{
  /** 
   * Get a single row based on the value of a field.
   */
  public function getRowByField ($field, $value, $ashash=false, $cols=null)
  {
    $where = "$field = :value";
    $data  = [':value'=>$value];
    $what  = 
      ['where'=>$where, 'data'=>$data, 'single'=>true, 'rawRow'=>$ashash];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /** 
   * Get a single row based on the value of multiple fields.
   *
   * This is a simple AND based approach, and uses = as the comparison.
   * If you need anything more complex, write your own method, or use
   * getRowWhere() instead.
   */
  public function getRowByFields ($fields, $ashash=False, $cols=null)
  {
    $what = ['where'=>$fields, 'rawRow'=>$ashash, 'single'=>true];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /** 
   * Get a single row, specifying the WHERE clause and bound data.
   */
  public function getRowWhere ($where, $data=[], $ashash=False, $cols=null)
  {
    $what = ['where'=>$where, 'data'=>$data, 'rawDocument'=>$ashash, 'single'=>true];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /**
   * Return a result set using a hand crafted SQL statement.
   */
  public function listRows ($stmt, $data, $cols=Null)
  {
    $what = ['where'=>$stmt, 'data'=>$data];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /**
   * Return a result set using a map of fields.
   */
  public function listByFields ($fields, $cols=Null, $append=Null, $data=null)
  {
    $what = ['where'=>$fields];
    if (isset($data))
      $what['data'] = $data;
    if (isset($cols))
      $what['cols'] = $cols;
    if (isset($append))
      $what['append'] = $append;
    return $this->select($what);
  }

}
