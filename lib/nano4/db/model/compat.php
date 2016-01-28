<?php

namespace Nano4\DB\Model;

/**
 * Compatibility wrapper for the old DB\Model methods.
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
      ['where'=>$where, 'data'=>$data, 'single'=>true, 'asHash'=>$ashash];
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
    $what = ['where'=>$fields, 'asHash'=>$ashash, 'single'=>true];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /** 
   * Get a single row, specifying the WHERE clause and bound data.
   */
  public function getRowWhere ($where, $data=[], $ashash=False, $cols=null)
  {
    $what = ['where'=>$where, 'data'=>$data, 'asHash'=>$ashash, 'single'=>true];
    if (isset($cols))
      $what['cols'] = $cols;
    return $this->select($what);
  }

  /**
   * Return a result set using a hand crafted SQL statement.
   */
  public function listRows ($stmt, $data, $cols=Null)
  {
    // TODO: finish this.
    $cols = $this->get_cols($cols);
    $query = "SELECT $cols FROM {$this->table} $stmt";
#    error_log($query);
    return $this->execute($query, $data);
  }

  /**
   * Return a result set using a map of fields.
   */
  public function listByFields ($fields, $cols=Null, $append=Null, $data=[])
  {
    // TODO: finish this.
    if (isset($fields))
    {
      $stmt  = "WHERE ";
      $stmt .= $this->buildWhere($fields, $data);
    }
    else
    {
      $stmt = '';
    }
    if (isset($append))
    {
      $stmt .= " $append";
    }
    return $this->listRows($stmt, $data, $cols);
  }

}
