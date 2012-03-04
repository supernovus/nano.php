#!/usr/bin/env php
<?php

// Example adduser script.

$shortopts = "u:p:r:a";
$opts = getopt($shortopts);

if (!$opts || !isset($opts['u']) || !isset($opts['p']))
{ 
  echo "usage: ./adduser.php -u <username> -p <password> [-r <realname>] [-a]\n";
  exit;
}

$user = $opts['u'];
$pass = $opts['p'];
$hash = sha1(trim($user.$pass));
if (isset($opts['r']))
  $realname = $opts['r'];
else
{ $email = split('@', $user);
  $realname = ucfirst($email[0]);
}

require_once 'lib/nano3/init.php';
$nano  = \Nano3\get_instance();
$nano->conf->loadInto('db', './conf/db.json', 'json'); 
$users = $nano->models->load('users', $nano->conf->db);

$users->newUser($user, $realname, $pass);

echo "Added $user to the database.\n";

## End of script.
