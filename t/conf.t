<?php

namespace Test;

require_once 'lib/nano/init.php';
require_once 'lib/test.php';

$nano = \Nano\initialize();

$nano->conf->setDir('t/conf/');

use Nano\Utils\Arry;

plan(28);

$simple = $nano->conf->simple;

ok(is_array($simple), 'Simple structure is an array.');

is ($simple['hello'], 'World', 'Simple string.');

$assoc = $simple['forest'];
ok (Arry::is_assoc($assoc), 'Associative array child.');
is ($assoc['trees'], true, 'True value.');
is ($assoc['flowers'], false, 'False value.');
is ($assoc['streams'], 0.231, 'Float value.');

$seq = $simple['group'];
ok ((is_array($seq) && !Arry::is_assoc($seq)), 'Sequential array child.');
is ($seq[0], 0, 'Int value from start of sequence.');
is ($seq[8], 21, 'Int value from end of sequence.');

$get1 = $nano->conf->getPath('/simple/forest/streams');
is ($get1, 0.231, 'getPath on simple configs.');

$includes = $nano->conf->includes;

ok(is_array($includes), 'Includes structure is an array.');
is($includes['name'], 'Include top', 'Top level property not overridden.');
is($includes['foo'], 'FOO!', 'Included property order correct.');
is($includes['note'], 'There is no second.', 'Handles missing includes.');

$nested = $nano->conf->nested;

ok(is_object($nested), 'Nested structure is an object.');
is($nested->subconf['another'], 'Level', 'Nested property is correct.');

$get2 = $nested->getPath('/subconf/another');
is($get2, 'Level', 'getPath on nested configs.');

$get3 = $nested->getPath('//simple/group/8');
is($get3, 21, 'getPath root syntax.');

$get4 = $nested->getPath('../simple/goodbye');
is($get4, 'Universe', 'getPath parent syntax.');

$uses = $nano->conf->uses;

ok(is_array($uses), 'Uses structure is an array.');
ok(!isset($uses['@useDepth']), '@useDepth parsed and removed.');
ok(!isset($uses['one']['@use']), '@use statement parsed and removed.');
is($uses['one']['name'], 'First', 'Single trait inclusion works.');
is($uses['two']['name'], 'Second', 'Single trait include works again.');
is($uses['three']['name'], 'Third', 'Use overrides work.');
is($uses['three']['foo'], 'Bar', 'Use order works.');
is($uses['three']['baz'], 'Doh', 'Multiple @use traits work.');
is($uses['three']['note'], 'Unlisted', 'Direct trait inclusion works.');
