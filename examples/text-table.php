<?php

namespace Test;

require_once 'lib/nano/init.php';

\Nano\register();

use Nano\Utils\Text as T;

$table = new T\Table(
[
  'addline' => T\LINE_BEFORE,
  'headerColor' => T\Colors::LIGHT_YELLOW,
  'columns' =>
  [
    ['length'=>8],
    ['length'=>32, 'align'=>T\ALIGN_RIGHT],
    ['align'=>T\ALIGN_CENTER],
  ],
]);

$text = $table->addHeader(['One','Two','Three'], true);
$text .= $table->addRow(['Hello world', 'First one', 'Foo bar']);
$text .= $table->addRow(['Goodbye', 'Another one', 'Bar foo']);
$text .= $table->addRow(['It\'s the end of the', 'world as we know', 'it, and I feel fine.']);
$text .= $table->addBottom();

echo $text;
