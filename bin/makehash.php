#!/usr/bin/env php
<?php

$shortopts = "u:p:";
$opts = getopt($shortopts);

if (!$opts || !isset($opts['u']) || !isset($opts['p']))
{ echo "usage: ./makehash.php -u <username> -p <password>\n";
  exit;
}

$user = $opts['u'];
$pass = $opts['p'];
$hash = sha1(trim($user.$pass));

echo $hash;
echo "\n";

## End of script.