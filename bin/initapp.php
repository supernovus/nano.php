#!/usr/bin/env php
<?php

/**
 * Command line script to create a new application skeleton from a template.
 *
 * Run it from the main Nano4 directory, as in:
 *
 *   ./bin/initapp.php
 *
 */

namespace Nano4;

function invalid_settings ()
{
	die("the specified template has invalid settings");
}

function error ($err)
{
	error_log("error: $err\n");
	exit;
}

function usage ($err=null)
{
	global $argv;
	if ($err)
		error_log("error: $err\n");
	echo <<<"ENDOFTEXT"
usage: {$argv[0]} <command> [options...]

Commands:

 -L                    List the available application templates.
 -M <template_name>    List the modules and requirements for the template.
 -A <target_dir>       Create a new application in the target directory.

Options for -A:

 -t <template_name>    Choose the template you want to use. <mandatory>

 -m <module> ...       Choose the modules you want.
                       Some modules may be combined, some may not.

 -j <path_to_nanojs>   Use Nano.js in the application.

 -c                    Copy the Nano.php and Nano.js files instead of linking.

ENDOFTEXT;
	exit;
}

$opts = getopt('LA:M:t:m:j:c');

if (!isset($opts['L']) && !isset($opts['M']) && !isset($opts['A']))
{
	usage();
}

if (isset($opts['A']) && !isset($opts['t']))
{
	usage("when using -A, the -t option is mandatory.");
}

require_once 'lib/nano4/init.php';

$nano = initialize();

$nano->conf->setDir('./skel', true);

if (isset($opts['L']))
{ // Get a list of available templates and template modules.
	foreach ($nano->conf as $confname => $conf)
	{
		$dirname = "./skel/$confname.d";
		$conf = $nano->conf[$confname]->template;

		echo "'$confname'\n";
		echo $conf['desc']."\n";
	}
	exit;
}

$nanodir = getcwd();
$nanolib = "$nanodir/lib";
$nanobin = "$nanodir/bin";

if (isset($opts['M']))
	$template = $opts['M'];
else
	$template = $opts['t'];

$tdir = "$nanodir/skel/$template.d";
if (!file_exists($tdir))
{
	usage("invalid template '$template' specified.");
}
$conf = $nano->conf[$template]->template;

if (isset($opts['M']))
{
	echo "'$template'\n";
	if (isset($conf['requires']))
	{
		echo "  Requires: ".join(', ', $conf['requires'])."\n";
	}
	if (isset($conf['modules']))
	{
		echo "  Modules:\n";
		foreach ($conf['modules'] as $modname => $module)
		{
			echo "    '$modname'\n";
			echo "      ".$module['desc']."\n";
			if (isset($module['provides']))
			{
				echo "      Provides: ".join(', ', $module['provides'])."\n";
			}
		}
	}
	else
	{
		echo "  No modules.\n";
	}
	exit;
}

$target = $opts['A'];
$copymode = isset($opts['c']);

if (file_exists($target))
{
	usage("the '$target' directory already exists, cannot continue.");
}

$modules = null;
$provides = [];
if (isset($conf['modules']) && isset($opts['m']))
{
	$modules = [];
	$amodules = $conf['modules'];
	if (is_string($opts['m']))
		$tmodules = [$opts['m']];
	elseif (is_array($opts['m']))
		$tmodules = $opts['m'];
	else
		usage("invalid -m paramter passed");
	// Let's get a list of provides and make sure there are no duplicates.
	foreach ($tmodules as $modname)
	{
		if (!isset($amodules[$modname]))
		{
			usage("unknown module '$modname' specified in -m parameter.");
		}
		$module = $amodules[$modname];
		if (isset($module['provides']))
		{
			foreach ($module['provides'] as $provide)
			{
				if (isset($provides[$provide]))
				{
					usage("mutually exclusive -m options both provide '$provide'.");
				}
				else
				{
					$provides[$provide] = true;
				}
			}
		}
		$modules[] = $module;
	}
}

// Now let's make sure any required module provides are fullfilled.
if (isset($conf['requires']))
{
	foreach ($conf['requires'] as $require)
	{
		if (!isset($provides[$require]))
		{
			usage("missing a module providing required '$require' resource.");
		}
	}
}

// If we made it this far, the initial consistency checks are done.

$appid = strtolower(basename($target));
$appns = ucfirst($appid);

$tempns = $conf['appname'];
$tempid = strtolower($tempns);

$basedir = $tdir/$conf['base'];
if (!file_exists($basedir))
{
	invalid_settings();
}

mkdir($target, 0755, true);
system("rsync -a $basedir/ $target/");

if (file_exists("$target/lib/$tempid"))
{
	rename("$target/lib/$tempid", "$target/lib/$appid");
	system("find $target/lib/$tempid -name '*.php' | xargs perl -pi -e \"s/$tempns/$appns/g\"");
}

function put_file ($src, $tgt)
{
	global $copymode;
	if ($copymode)
	{
		copy($src, $tgt);
	}
	else
	{
		symlink($src, $tgt);
	}
}

if (isset($conf['bin']))
{
	mkdir("$target/bin", 0755, true);
	foreach ($conf['bin'] as $srcname => $tgtname)
	{
		if (is_bool($tgtname))
			$tgtname = $srcname;
		put_file("$nanobin/$srcname", "$target/bin/$tgtname");
	}
}

if (!file_exists("$target/lib"))
{
	mkdir("$target/lib", 0755, true);
}

function put_tree ($src, $tgt, $contents=false)
{
	global $copymode;
	if ($copymode)
	{
		system("rsync -a $src/ $tgt/");
	}
	elseif ($contents)
	{
		mkdir($tgt, 0755, true);
		system("ln -s $src/* $tgt/");
	}
	else
	{
		symlink($src, $tgt);
	}
}

put_tree("$nanolib/nano4", "$target/lib/nano4");

if (isset($opts['j']) && isset($conf['nano.js']))
{
	$jspath = $opts['j'];
	if (!file_exists($jspath))
	{
		error("'$jspath' does not exist, skipping Nano.js installation.");
	}

	$jsconf = $conf['nano.js'];
	$scriptdest = $jsconf['scripts'];
	$styledest  = $jsconf['styles'];
	$dogrunt    = $jsconf['grunt'];
	$domods     = $jsconf['modules'];

	put_tree("$jspath/scripts", "$target/$scriptdest", true);
	put_tree("$jspath/style",   "$target/$styledets",  true);
	if ($dogrunt)
	{
		put_file("$jspath/Gruntfile.js", "$target/Gruntfile.js");
		if (!file_exists("$target/grunt"))
		{
			mkdir("$target/grunt");
			system("rsync -a $jspath/grunt/ $target/grunt/");
			echo "==> You will need to customize the $target/grunt/* files";
		}
	}
	if ($domods)
	{
		put_tree("$jspath/node_modules", "$target/node_modules", true);
	}
}

