#!/usr/bin/env php
<?php

/**
 * Command line script to add or edit a user.
 * By default users log in using their e-mail address.
 * If you change that to a user name, change references below.
 */

namespace Example;

function usage ($error=null)
{
  global $argv;
  if ($error)
    echo "error: $error\n";
  echo <<<"ENDOFTEXT"
usage: {$argv[0]} <command>

Commads:

 -l                  List known users.
 -a <email_address>  Add a user.
 -e <email_address>  View or edit a user.
 -d <email_address>  Delete a user.

Options for -a and -e commands:

  -n <string>    The real name of the user.
  -p             Generate a random password for the user.
  -p=<string>    Set the specified password for the user.
  -f <json>      JSON object of fields to set on the user.
  -F <file>      JSON file of fields to set on the user.

Options for -e only:

  -E <string>    Change the e-mail address associated with the user.

ENDOFTEXT;
  exit;
}

$opts = getopt('la:e:d:n:p::f:F:');

if (
  !isset($opts['a']) &&
  !isset($opts['e']) && 
  !isset($opts['l']) &&
  !isset($opts['d'])
)
{
  usage();
}

require_once 'lib/example/bootstrap.php';

$nano_opts =
[
  'classroot' => 'lib',
  'viewroot'  => 'views',
  'confroot'  => 'conf',
  'default_lang' => 'en',
];

$nano = Bootstrap::stage1($nano_opts);
Bootstrap::stage2($nano);

$ctrl = $nano->controllers->load('auth');

$users = $ctrl->model('users');

if (isset($opts['l']))
{
  $userlist = $users->listUsers();
  echo $userlist->to_json(true)."\n";
  exit;
}
elseif (isset($opts['d']))
{
  $email = $opts['d'];
  $user = $users->getUser($email);
  if (isset($user))
  {
    $user->delete();
    echo ">>> Deleted user.\n";
  }
  else
  {
    echo "!!! Specified user does not exist.\n";
  }
  exit;
}
elseif (isset($opts['a']))
{
  $email = $opts['a'];
  $add = true;
}
elseif (isset($opts['e']))
{
  $email = $opts['e'];
  $add = false;
}
else
{
  usage("how did you get here?");
}

$user = $users->getUser($email);

function random_pass ()
{
  $newpass = uniqid();
  echo ">>> Password: $newpass\n";
  return $newpass;
}

$newpass = null;
$userflags = null;
if (isset($opts['p']))
{ // We are resetting password.
  if (is_string($opts['p']))
  {
    $newpass = $opts['p'];
  }
  else
  {
    $newpass = random_pass();
  }
}
elseif ($add)
{
  $newpass = random_pass();
}

if (isset($opts['f']))
{
  $userflags = json_decode($opts['f'], true);
}
elseif (isset($opts['F']) && file_exists($opts['F']))
{
  $userflags = json_decode(file_get_contents($opts['F']), true);
}

if (isset($user))
{ // The user exists, we are editing.
  $changed = false;
  if (isset($newpass))
  {
    $user->changePassword($newpass, false);
    $changed = true;
  }
  if (isset($opts['n']))
  {
    $user->name = $opts['n'];
    $changed = true;
  }
  if (isset($userflags))
  {
    foreach ($userflags as $key => $val)
    {
      $user[$key] = $val;
    }
    $changed = true;
  }
  if (isset($opts['E']))
  {
    $newmail = $opts['E'];
    $ok = $user->changeLogin($newmail, false);
    if ($ok)
    {
      $changed = true;
    }
    else
    {
      echo "::: Could not change e-mail, as it's already used.\n";
    }
  }
  if ($changed)
  {
    echo ">>> User details updated.\n";
    $user->save();
  }
  echo ">>> User details:\n";
  echo $user->to_json(true)."\n";
}
else
{
  if ($add)
  {
    if (isset($userflags))
      $udef = $userflags;
    else
      $udef = [];
    $udef['email'] = $email;
    if (isset($opts['n']))
      $udef['name'] = $opts['n'];

    $user = $users->addUser($udef, $newpass, true);

    if (isset($user))
    {
      echo ">>> Created user:\n";
      echo $user->to_json(true)."\n";
    }
    else
    {
      echo "!!! Error adding user.\n";
    }
  }
  else
  {
    echo "!!! Specified user does not exist.\n";
  }
}

