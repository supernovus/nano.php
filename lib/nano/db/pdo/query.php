<?php

namespace Nano\DB\PDO;

const Q_AND = ' AND ';
const Q_OR  = ' OR ';

function q_id ($name)
{
  return $name.'_'.uniqid();
}

/**
 * The Queryable base class. Both Query and Subquery inherit from this.
 */
abstract class Queryable
{
  protected $parent;
  protected $root;
  protected $join = Q_AND;
  protected $op = '=';

  protected $where = [];
  protected $wdata = [];

  /**
   * Build a Queryable class
   *
   * @param array $opts   An associative array of parameters.
   *
   * Required parameters:
   *
   *  'parent'    The parent object that called us.
   *  'root'      The Query object at the top of the stack.
   *
   * Optional parameters:
   *
   *  'join'      The current WHERE joining operator.
   *              One of the Q_AND or Q_OR namespace constants.
   *              Default: Q_AND
   *
   *  'op'        The default comparison operator.
   *              Default '='
   *
   */
  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
      $this->parent = $opts['parent'];
    if (isset($opts['root']))
      $this->root = $opts['root'];
    if (isset($opts['join']))
      $this->join = $opts['join'];
    if (isset($opts['op']))
      $this->op = $opts['op'];
  }

  public function reset ()
  {
    $this->join  = Q_AND;
    $this->op    = '=';
    $this->where = [];
    $this->wdata = [];
  }

  /**
   * Use AND between associative array assignments.
   */
  public function withAnd ()
  {
    $this->join = Q_AND;
    return $this;
  }

  public function withOr ()
  {
    $this->join = Q_OR;
    return $this;
  }

  public function withOp ($op)
  {
    $this->op = $op;
    return $this;
  }

  public function where ($val1=null, $val2=null, $val3=null)
  {
    if (isset($val1))
    {
      $op = $this->op;
      if (is_string($val1) && isset($val2))
      {
        $ph = q_id($val1);
        if (isset($val3))
        { // We're in 'field','=','value' mode.
          $this->where[] = "$val1 $val2 :$ph";
          $this->wdata[$ph] = $val3;
        }
        else
        { // We're in 'field','value' mode.
          $this->where[] = "$val1 $op :$ph";
          $this->wdata[$ph] = $val2;
        }
      }
      elseif (is_array($val1))
      { // We're in ['field'=>'val'] mode.
        $join = $this->join;
        $cnt = count($val1);
        $c = 0;
        foreach ($val1 as $k => $v)
        {
          $ph = q_id($k);
          $this->where[] = "$k $op :$ph";
          $this->wdata[$ph] = $v;
          if ($c != $cnt - 1)
            $this->where[] = $join;
          $c++;
        }
      }
    }
    else
    { // We're creating a sub-query.
      $subquery = $this->subquery();
      $this->where[] = $subquery;
      return $subquery;
    }
    return $this;
  }

  /**
   * Add a bit to the WHERE statement using AND
   */
  public function and ($val1=null, $val2=null, $val3=null)
  {
    $this->where[] = Q_AND;
    return $this->where($val1,$val2,$val3);
  }

  /**
   * Add a bit to the WHERE statement using OR
   */
  public function or ($val1=null, $val2=null, $val3=null)
  {
    $this->where[] = Q_OR;
    return $this->where($val1,$val2,$val3);
  }

  /**
   * Compile the WHERE statement.
   */
  public function get_where ()
  {
    $where = '';
    foreach ($this->where as $w)
    {
      if (is_string($w))
      {
        $where .= $w;
      }
      elseif (is_object($w) && is_callable([$w, 'get_where']))
      {
        $subwhere = $w->get_where();
        $where .= '(' . $subwhere[0] . ')';
        foreach ($subwhere[1] as $k => $v)
        {
          $this->wdata[$k] = $v;
        }
      }
    }
    $wdata = $this->wdata;
    return [$where, $wdata];
  }

  /**
   * Get a Subquery.
   */
  public function subquery ($opts=[])
  {
    $opts['parent'] = $this;
    $opts['root']   = $this->root;
    return new Subquery($opts);
  }

  /**
   * Get the direct parent of this object.
   */
  public function back ()
  {
    return $this->parent;
  }

  /**
   * Get the top-most Query object.
   */
  public function root ()
  {
    return $this->root;
  }
}

class Query extends Queryable
{
  public $cols;
  public $order;
  public $limit;
  public $offset;
  public $single;
  public $columnData;

  public $fetch;

  public $rawRow;
  public $rawResults;
 
  public function __construct ($opts=[])
  {
    parent::__construct($opts);
    $this->root = $this;
  }

  public function reset ()
  {
    unset($this->cols);
    unset($this->order);
    unset($this->limit);
    unset($this->offset);
    unset($this->single);
    unset($this->columnData);
    unset($this->rawRow);
    unset($this->rawResults);
    unset($this->fetch);
    parent::reset();
  }

  public function get ($cols)
  {
    $this->cols = $cols;
    return $this;
  }

  public function select ($cols)
  {
    return $this->get($cols);
  }

  public function set ($coldata)
  {
    $this->columnData = $coldata;
    return $this;
  }

  public function insert ($coldata)
  {
    return $this->set($coldata);
  }

  public function update ($coldata)
  {
    return $this->set($coldata);
  }

  public function order ($order)
  {
    $this->order = $order;
    return $this;
  }

  public function sort ($order)
  {
    return $this->order($order);
  }

  public function limit ($limit)
  {
    $this->limit = $limit;
    return $this;
  }

  public function offset ($offset)
  {
    $this->offset = $offset;
    return $this;
  }

  public function single ($single=true)
  {
    $this->single = $single;
    return $this;
  }

  public function raw ()
  {
    $this->rawRow = true;
    $this->rawResults = true;
    return $this;
  }

  public function asArray()
  {
    $this->fetch = \PDO::FETCH_NUM;
    return $this;
  }

  public function asBoth()
  {
    $this->fetch = \PDO::FETCH_BOTH;
    return $this;
  }

}

/**
 * A Subquery represents a nested \portion of a WHERE statement.
 * 
 * A Subquery is not constructed directly, but by using the
 * $query->subquery() method.
 */
class Subquery extends Queryable
{
  /**
   * Any method we don't recognize, pass to the root.
   */
  public function __call ($method, $args)
  {
    $root = $this->root();
    call_user_func_array([$root, $method], $args);
  }
}
