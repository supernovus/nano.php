<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

\Nano\register();

plan(16);

$in =
[
  'postfix' =>
  [
    1,
    2,
    'gt',
    'not',
    4,
    4,
    'eq',
    'and',
  ],
  'prefix' =>
  [
    'and',
    'not',
    'gt',
    1,
    2,
    'eq',
    4,
    4,
  ],
  'infix' =>
  [
    '(',
      '(',
        'not',
        '(',
          1,
          'gt',
          2,
        ')',
      ')',
      'and',
      '(',
        4,
        'eq',
        4,
      ')',
    ')'
  ],
];

// A 'loose' infix expression, depending on precedence rules.
$loose_infix = ['not', 1, 'gt', 2, 'and', 4, 'eq', 4];

// TODO: adjust these once unary operator support is in properly.
$operators =
[
  'eq'  => ['precedence'=>3],
  'gt'  => ['precedence'=>3],
  'and' => ['precedence'=>1],
  'not' => ['precedence'=>2, 'unary'=>true],
];

$expy = new \Nano\Utils\Expression\Parser(['operators'=>$operators]);

$to_types = array_keys($in);

$want_parsed = '[{"op":{"name":"and","operands":2,"precedence":1,"assoc":1},"items":[{"op":{"name":"not","operands":1,"precedence":2,"assoc":1},"items":[{"op":{"name":"gt","operands":2,"precedence":3,"assoc":1},"items":[1,2]}]},{"op":{"name":"eq","operands":2,"precedence":3,"assoc":1},"items":[4,4]}]}]';

// First, let's do the 12 automated conversion tests.
foreach ($in as $type => $exp_in)
{
  $meth = 'load'.ucfirst($type);
  $expy->$meth($exp_in);
  $parsed_obj = $expy->getData();
  $parsed_json = json_encode($parsed_obj);
  is($parsed_json, $want_parsed, "parsed $type");
  foreach ($to_types as $to_type)
  {
    $tometh = 'save'.ucfirst($to_type);
    $saved = $expy->$tometh();
    is($saved, $in[$to_type], "$type to $to_type");
  }
}

// Now let's try the loose infix parsing.
$expy->loadInfix($loose_infix);
$parsed_obj = $expy->getData();
$parsed_json = json_encode($parsed_obj);
is($parsed_json, $want_parsed, "parsed loose infix");
foreach ($to_types as $to_type)
{
  $tometh = 'save'.ucfirst($to_type);
  $saved = $expy->$tometh();
  is($saved, $in[$to_type], "loose infix to $to_type");
}

