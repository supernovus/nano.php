<?php

namespace Test;

require_once 'lib/nano/init.php';

\Nano\register();

use Nano\Utils\Text\Colors as C;

echo C::fg('red')."Warning".C::NORMAL."\n";
echo C::bg('red')."Extra warning".C::NORMAL."\n";
echo C::get(['fg'=>'yellow','bg'=>'blue'])."Hello world".C::get()."\n";
