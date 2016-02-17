<?php

namespace Nano4\DB;

require_once 'lib/nano4/init.php';

\Nano4\register();

$db = new Simple(['dsn'=>'fakedb:dbname=foo', 'noConnect'=>true]);
$query = new Query();

$query->get(['name','job'])->where('age','>','19')->and('age','<','50')->order('name')->limit(10)->offset(0);

function showres ($res)
{
  if (count($res) >= 2)
  {
    $sql = $res[0];
    $info = [];
    $info['data'] = $res[1];
    if (count($res) >= 4)
    {
      $info['whereData'] = $res[2];
      $info['columnData'] = $res[3];
    }
    $infoText = json_encode($info, \JSON_PRETTY_PRINT);
    echo "$sql\n";
    echo "$infoText\n";
  }
  else
  {
    error_log("invalid results returned");
  }
}

$res = $db->select('employees', $query);
showres($res);

$query->reset();
$query->get('id')->where('project',123)->and()->where(['type'=>2,'class'=>1])->or('level','>',3)->sort('project ASC, id DESC');

$res = $db->select('documents', $query);
showres($res);


