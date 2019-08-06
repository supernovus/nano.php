<?php

namespace Nano\Utils\Expression;

const ASSOC_NONE  = 0;
const ASSOC_LEFT  = 1;
const ASSOC_RIGHT = 2;

class Parser
{
  protected $data;
  protected $operators = [];

  protected $lp = '(';
  protected $rp = ')';

  public function __construct ($opts=[])
  {
    if (isset($opts['operators']) && is_array($opts['operators']))
    {
      foreach ($opts['operators'] as $opname => $opopts)
      {
        $this->addOperator($opname, $opopts);
      }
    }

    if (isset($opts['lp']) && is_string($opts['lp']))
    {
      $this->lp = $opts['lp'];
    }
    if (isset($opts['rp']) && is_string($opts['rp']))
    {
      $this->rp = $opts['rp'];
    }
  }

  public function addOperator ($name, $opts=[])
  {
    if ($name instanceof Operator)
    {
      $this->operators[$name->name] = $name;
    }
    elseif (is_string($name))
    {
      $this->operators[$name] = new Operator($name, $opts);
    }
    else
    {
      throw new \Exception("addOperator must be sent a name, or an Operator instance.");
    }
  }

  public function loadInfix (Array $data)
  { // Convert to postfix using Shunting-Yard, then parse that data.
    // TODO: Handle unary operators differently.
    $this->data = [];
    $operators = [];
    $operands  = [];
    $len = count($data);
    for ($c = 0; $c < $len; $c++)
    {
      $v = $data[$c];
      if (isset($this->operators[$v]))
      { // It's an operator.
        $op = $this->operators[$v];
        $op2 = end($operators);
        while ($op2 
          && $op2 !== $this->lp 
          && (
            ($op->leftAssoc() && $op->precedence <= $op2->precedence)
            ||
            ($op->rightAssoc() && $op->precedence < $op2->precedence)
          )
        )
        {
          $operands[] = array_pop($operators)->name;
          $op2 = end($operators);
        }
        $operators[] = $op;
      }
      elseif ($v === $this->lp)
      { // It's a left paranthesis.
        $operators[] = $v;
      }
      elseif ($v === $this->rp)
      { // It's a right paranthesis.
        while (end($operators) !== $this->lp)
        {
          $operands[] = array_pop($operators)->name;
          if (!$operators)
          {
            throw new \Exception('Mismatched parenthesis');
          }
        }
        array_pop($operators);
      }
      else
      { // It's an operand.
        $operands[] = $v;
      }
    }
    while ($operators)
    {
      $op = array_pop($operators);
      if ($op === $this->lp)
      {
        throw new \Exception('Mismatched perenthesis');
      }
      $operands[] = $op->name;
    }
    //error_log("infix to postfix: ".json_encode($operands));
    return $this->loadPostfix($operands);
  }

  public function loadPrefix (Array $data)
  {
    $this->data = [];
    $len = count($data);
    for ($c = $len-1; $c >= 0; $c--)
    {
      $v = $data[$c];
      if (isset($this->operators[$v]))
      { // It's an operator, do the thing.
        $op = $this->operators[$v];
        $s = $op->operands;
        $z = count($this->data);
        if ($z < $s)
        {
          throw new \Exception("Operator $v requires $s operands, only $z found.");
        }
        $substack = array_reverse(array_splice($this->data, $z-$s, $s));
        $this->data[] = new Condition($op, $substack);
      }
      else
      { // It's an operand, add it to the stack.
        $this->data[] = $v;
      }
    }
  }

  public function loadPostfix (Array $data)
  {
    $this->data = [];
    $len = count($data);
    for ($c = 0; $c < $len; $c++)
    {
      $v = $data[$c];
      if (isset($this->operators[$v]))
      { // It's an operator, do the thing.
        $op = $this->operators[$v];
        $s = $op->operands;
        $z = count($this->data);
        if ($z < $s)
        {
          throw new \Exception("Operator $v requires $s operands, only $z found.");
        }
        $substack = array_splice($this->data, $z-$s, $s);
        $this->data[] = new Condition($op, $substack);
      }
      else
      { // It's an operand, add it to the stack.
        $this->data[] = $v;
      }
    }
  }

  public function getData ()
  {
    return $this->data;
  }

  public function saveInfix ()
  {
    $in = $this->data;
    $out = [];
    foreach ($in as $item)
    {
      $this->serialize_infix_item($item, $out);
    }
    return $out;
  }

  protected function serialize_infix_item ($item, &$out)
  {
    if ($item instanceof Condition)
    {
      $this->serialize_infix_condition($item, $out);
    }
    else
    {
      $out[] = $item;
    }
  }

  protected function serialize_infix_condition ($item, &$out)
  {
    $out[] = $this->lp;
    $opn = $item->op;
    $ops = count($item->items);
    if ($ops == 2)
    {
      $this->serialize_infix_item($item->items[0], $out);
      $out[] = $opn->name;
      $this->serialize_infix_item($item->items[1], $out);
    }
    elseif ($ops == 1)
    {
      $out[] = $opn->name;
      $this->serialize_infix_item($item->items[0], $out);
    }
    else
    {
      throw new \Exception("Operator must have only 1 or 2 operands, $opn has $ops which is invalid.");
    }
    $out[] = $this->rp;
  }

  public function savePrefix ()
  {
    $in = $this->data;
    $out = [];
    $this->serialize_prefix($in, $out);
    return $out;
  }

  protected function serialize_prefix (&$in, &$out)
  {
    foreach ($in as $item)
    {
      if ($item instanceof Condition)
      { // It's an operator.
        $out[] = $item->op->name;
        $this->serialize_prefix($item->items, $out);
      }
      else
      { // It's an operand.
        $out[] = $item;
      }
    }
  }

  public function savePostfix ()
  {
    $in = $this->data;
    $out = [];
    $this->serialize_postfix($in, $out);
    return $out;
  }

  protected function serialize_postfix (&$in, &$out)
  {
    foreach ($in as $item)
    {
      if ($item instanceof Condition)
      { // It's an operator.
        $this->serialize_postfix($item->items, $out);
        $out[] = $item->op->name;
      }
      else
      { // It's an operand.
        $out[] = $item;
      }
    }
  }

  public function evaluate ()
  {
    if (count($this->data) > 1)
    {
      throw new \Exception("Expression does not parse to a single top item, cannot evaluate.");
    }
    $topItem = $this->data[0];
    if ($topItem instanceof Condition)
    {
      return $topItem->evaluate();
    }
    else
    {
      return $topItem;
    }
  }

}

class Operator
{
  public $name;
  public $operands   = 2;
  public $precedence = 1;
  public $assoc      = ASSOC_LEFT;

  protected $evaluator;

  public function __construct ($name, $opts=[])
  {
    $this->name = $name;
    if (isset($opts['unary']) && $opts['unary'])
    {
      $this->operands = 1;
      $this->assoc = ASSOC_RIGHT;
    }

    if (isset($opts['operands']) && is_int($opts['operands']))
    {
      $this->operands = $opts['operands'];
    }

    if (isset($opts['precedence']) && is_numeric($opts['precedence']))
    {
      $this->precedence = $opts['precedence'];
    }

    if (isset($opts['assoc']))
    {
      if (is_numeric($opts['assoc']))
      {
        $this->assoc = $opts['assoc'];
      }
      elseif (is_string($opts['assoc']))
      {
        $assocStr = strtolower(substr($opts['assoc'], 0, 1));
        if ($assocStr == 'l')
        {
          $this->assoc = ASSOC_LEFT;
        }
        elseif ($assocStr == 'r')
        {
          $this->assoc = ASSOC_RIGHT;
        }
        else
        {
          $this->assoc = ASSOC_NONE;
        }
      }
      elseif ($opts['assoc'] === false)
      {
        $this->assoc = ASSOC_NONE;
      }
    }

    if (isset($opts['evaluate']))
    {
      if (is_callable($opts['evaluate']))
      { // Using a custom evaluator.
        $this->evaluator = $opts['evaluate'];
      }
      elseif (is_string($opts['evaluate']))
      { // Using a built-in evaluator.
        $this->setEvaluator($opts['evaluate']);
      }
      elseif ($opts['evaluate'] === true)
      { // Use the name as a built-in evaluator.
        $this->setEvaluator($name);
      }
      else
      {
        throw new \Exception("Invalid evaluator".$opts['evaluate']);
      }
    }
  }

  public function setEvaluator ($evaluator)
  {
    if (is_string($evaluator))
    {
      $evaluator = [$this, 'eval_'.strtolower($evaluator)];
    }
    if (is_callable($evaluator))
    {
      $this->evaluator = $evaluator;
    }
    else
    {
      throw new \Exception("Invalid evaluator passed to setEvaluator()");
    }
  }

  public function evaluate ($items)
  {
    if (!isset($this->evaluator))
    {
      throw new \Exception("Attempt to evaluate an operator without a handler.");
    }
    if (count($items) != $this->operands)
    {
      throw new \Exception("Invalid number of operands in operator evaluation.");
    }
    // Now make sure the items are scalar values, not objects.
    for ($i = 0; $i < count($items); $i++)
    {
      $item = $items[$i];
      if (is_object($item))
      { // It's a Condition, or a custom object.
        if (is_callable([$item, 'evaluate']))
        { // Get the value by evaluating it.
          $items[$i] = $item->evaluate();
        }
      }
    }
    // Okay, our items are all scalars now, let's do this.
    return call_user_func($this->evaluator, $items);
  }

  protected function eval_not ($items)
  { // Unary operator, only one item is used.
    return !($items[0]);
  }

  protected function eval_eq ($items)
  {
    return ($items[0] == $items[1]);
  }

  protected function eval_ne ($items)
  {
    return ($items[0] != $items[1]);
  }

  protected function eval_gt ($items)
  {
    return ($items[0] > $items[1]);
  }

  protected function eval_lt ($items)
  {
    return ($items[0] < $items[1]);
  }

  protected function eval_gte ($items)
  {
    return ($items[0] >= $items[1]);
  }

  protected function eval_lte ($items)
  {
    return ($items[0] <= $items[1]);
  }

  protected function eval_and ($items)
  {
    return ($items[0] and $items[1]);
  }

  protected function eval_or ($items)
  {
    return ($items[0] or $items[1]);
  }

  protected function eval_xor ($items)
  {
    return ($items[0] xor $items[1]);
  }

  protected function eval_add ($items)
  {
    return ($items[0] + $items[1]);
  }

  protected function eval_sub ($items)
  {
    return ($items[0] - $items[1]);
  }

  protected function eval_mult ($items)
  {
    return ($items[0] * $items[1]);
  }

  protected function eval_div ($items)
  {
    return ($items[0] / $items[1]);
  }

  protected function eval_neg ($items)
  { // Unary operator, only one item is used.
    return ($items[0] * -1);
  }

  public function leftAssoc ()
  {
    return $this->assoc === ASSOC_LEFT;
  }

  public function rightAssoc ()
  {
    return $this->assoc === ASSOC_RIGHT;
  }

  public function noAssoc ()
  {
    return $this->assoc === ASSOC_NONE;
  }
}

class Condition
{
  public $op;
  public $items;

  public function __construct (Operator $op, $items)
  {
    $this->op = $op;
    $this->items = $items;
  }

  public function evaluate ()
  {
    return $this->op->evaluate($this->items);
  }
}

