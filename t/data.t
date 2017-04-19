#!/usr/bin/env php
<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

$nano = \Nano\initialize();
$nano->pragmas->simpledom;

$simpleDOM = function_exists('simpledom_import_simplexml');

plan(12);

class Foo extends \Nano\Data\Arrayish
{
  public function load_simple_xml ($xml, $opts=null)
  {
    $this->tag_name = $xml->getName();
    if (isset($xml->hello))
      $this->hello = (string)$xml->hello;
    if (isset($xml['id']))
      $this->id = (int)(string)$xml['id'];
  }

  public function to_simple_xml ($opts=null)
  {
    $xopts = [];
    if (isset($this->tag_name))
      $xopts['default_tag'] = $this->tag_name;
    $xml = $this->get_simple_xml_element($xopts);
    if (isset($this->hello))
      $xml->addChild('hello', $this->hello);
    if (isset($this->id))
      $xml['id'] = $this->id;
    return $xml;
  }
}

class Bar extends Foo
{
  protected $newconst = true;
}

$json_in = <<<JEND
{
  "id": 1,
  "hello": "World"
}
JEND;

$foo = new Foo($json_in);
is ($foo->id, 1, 'JSON input had correct id property');
is ($foo->hello, 'World', 'JSON input had correct hello property');

$xml_out = <<<XEND
<?xml version="1.0"?>
<foo id="1"><hello>World</hello></foo>

XEND;

is ($foo->to_xml(), $xml_out, 'JSON input returned proper XML output');

$foo = new Foo($xml_out);

is ($foo->tag_name, 'foo', 'XML recycled input had correct tag name');
is ($foo->id, 1, 'XML recycled input had correct id');

$xml_in = <<<XEND
<?xml version="1.0"?>
<test>
  <hello>Universe</hello>
</test>

XEND;

$foo = new Foo($xml_in);

is ($foo->tag_name, 'test', 'XML input had correct tag name');
is ($foo->hello, 'Universe', 'XML input had correct hello property');

is ($foo->to_xml(['reformat'=>true]), $xml_in, 'XML full circle');

$bar = new Bar(['data'=>$json_in, 'parent'=>$foo]);

is ($bar->id, 1, 'Bar had correct id property');
is ($bar->parent(), $foo, 'Bar had correct parent');

$stests = 
[
  'SimpleDOM has proper class',
  'SimpleDOM serialized correctly',
];
if ($simpleDOM)
{
  $sdom1 = $bar->to_simple_dom();
  $sdom2 = $foo->to_simple_dom();
  is (get_class($sdom1), 'SimpleDOM', $stests[0]);
  $sdom1->appendChild($sdom2);
  $dom_out = <<<XEND
<?xml version="1.0"?>
<bar id="1">
  <hello>World</hello>
  <test>
    <hello>Universe</hello>
  </test>
</bar>

XEND;
  is ($sdom1->asPrettyXML(), $dom_out, $stests[1]);
}
else
{
  $smsg = 'SimpleDOM not found';
  foreach ($stests as $stest)
  {
    skip($smsg, $stest);
  }
}

