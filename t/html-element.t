<?php 

require_once "lib/nano4/init.php";
require_once "lib/test.php";

plan(4);

\Nano4\initialize(['no'=>true]);

$html = new \Nano4\Utils\HTML\Element('html');
$head = $html->head();
$head->title('A test page');
$head->link(['rel'=>'stylesheet', 'href'=>'main.css']);
$body = $html->body();
$body['onload'] = 'return true;';
$body->h1('Hello world');
$body->div(['id'=>'content'])->p("Welcome to the jungle, we've got fun and games.");

$got = "$html";
$expected="<html><head><title>A test page</title><link rel=\"stylesheet\" href=\"main.css\"/></head><body onload=\"return true;\"><h1>Hello world</h1><div id=\"content\"><p>Welcome to the jungle, we've got fun and games.</p></div></body></html>";

is ($got, $expected, "HTML constructed properly");

$dt = ['doctype'=>5];
$dt_str = '<!DOCTYPE html>';

$got2 = $html->to_html($dt);
$expected2 = $dt_str.$expected;

is ($got2, $expected2, "HTML with doctype works.");

$xmldec = '<?xml version="1.0"?>';
$xml = ['xml'=>true];

$got3 = $html->to_html($xml);
$expected3 = $xmldec.$expected;

is ($got3, $expected3, "HTML with XML declarator works.");

$got4 = $html->to_html($xml+$dt);
$expected4 = $xmldec.$expected2;

is ($got4, $expected4, "HTML with XML declarator and doctype works.");
