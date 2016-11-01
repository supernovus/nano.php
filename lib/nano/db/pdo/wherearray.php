<?php

namespace Nano\DB\PDO;

class WhereArray
{
  public $where;
  public $whereData;

  public function __construct ($array, $join='AND')
  {
    $this->where = $this->buildWhere($array, $this->whereData, $join);
  }

  /**
   * Build a WHERE statement.
   *
   * This is a fairly simplistic WHERE builder, that supports custom
   * comparison operators, multiple values, and a few other features.
   * 
   * If you need more than this, build your own custom query.
   */
  public static function buildWhere ($where, &$data, $join='AND')
  {
    if (is_array($where))
    {
      $stmt = [];
      foreach ($where as $key => $val)
      {
        if (is_array($val))
        {
          foreach ($val as $op => $subval)
          {
            $subc = 0;
            if (is_array($subval))
            {
              $subsubc = 0;
              $substmt = [];
              foreach ($subval as $subsubval)
              {
                $c = $key . '_' . $subc . '_' . $subsubc;
                $substmt[] = "$key $op :$c";
                $data[$c] = $subsubval;
                $subsubc++;
              }
              $stmt[] = '( ' . join(' OR ', $substmt) . ' )';
            }
            else
            {
              $c = $key . '_' . $subc;
              $stmt[] = "$key $op :$c";
              $data[$c] = $subval;
            }
            $subc++;
          }
        }
        elseif (isset($val))
        {
          $stmt[] = "$key = :$key";
          $data[$key] = $val;
        }
      }
      return join(" $join ", $stmt);
    }
    elseif (is_string($where))
    { // We assume the string is the raw WHERE statement.
      return $where;
    }
  }

}
