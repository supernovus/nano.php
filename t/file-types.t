<?php 

require_once "lib/nano4/init.php";
require_once "lib/test.php";

plan(2);

\Nano4\initialize(['no'=>true]);

$ft = new \Nano4\Utils\File\Types;

is ($ft->get('xml'), 'text/xml', 'get a simple type');

$xlxs = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
is ($ft->get('xlsx'), $xlxs, 'get a MS Office type');

