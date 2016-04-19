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

// Even though I personally use gulp 4, gulp 3 is still considered the 'stable'
// version, so it's going to be the default if the template doesn't specify one.
const DEFAULT_GULP = 3;

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

 -t <template_name>              Choose the template. (Required)

 -m <module> [-m <module2> ...]  Choose the modules for the template.
                                 Some modules may be combined, some may not.
                                 See the -M listings for details.

 -j <path_to_nanojs>             Use Nano.js in the application.

 -c                              Copy all files instead of using symlinks.

 -n <app_name>                   A name for your app, if not specified,
                                 it will be derived from the target_dir.

ENDOFTEXT;
	exit;
}

$opts = getopt('LA:M:t:m:j:cn:');

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
      if (isset($module['internal']) && $module['internal']) continue;
			echo "    '$modname'\n";
			echo "      ".$module['desc']."\n";
			if (isset($module['provides']))
			{
				echo "      Provides: ".join(', ', $module['provides'])."\n";
			}
      if (isset($module['default']))
      {
        if (is_array($module['default']))
        {
          echo "      Default for: ".join(', ', $module['default'])."\n";
        }
        elseif (is_bool($module['default']) && $module['default'])
        {
          echo "      Default for: ".join(', ', $module['provides'])."\n";
        }
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
$defaults = [];
if (isset($conf['modules']) && isset($opts['m']))
{
	$modules = [];
	$amodules = $conf['modules'];

  // Scan for default modules.
  foreach ($amodules as $module)
  {
    if (isset($module['provides'], $module['default']))
    {
      if (is_array($module['default']))
      {
        foreach ($module['default'] as $provide)
        {
          $defaults[$provide] = $module;
        }
      }
      elseif (is_bool($module['default']) && $module['default'])
      {
        foreach ($module['provides'] as $provide)
        {
          $defaults[$provide] = $module;
        }
      }
    }
  }

  // A function to add modules that were selected or used by other modules.
  function use_modules ($modlist, $where)
  {
    global $modules, $amodules;
  	foreach ($modlist as $modname)
  	{
  		if (!isset($amodules[$modname]))
  		{
  			usage("unknown module '$modname' specified in $where.");
  		}
  		$module = $amodules[$modname];
  		if (isset($module['provides']))
  		{
  			foreach ($module['provides'] as $provide)
  			{
  				if (isset($provides[$provide]))
  				{
  					usage("mutually exclusive modules both provide '$provide'.");
  				}
  				else
  				{
  					$provides[$provide] = true;
  				}
  			}
  		}
      if (isset($module['use']))
      {
        use_modules($module['use'], "$modname module definition");
      }
  		$modules[] = $module;
  	}
  }

	if (is_string($opts['m']))
		$tmodules = [$opts['m']];
	elseif (is_array($opts['m']))
		$tmodules = $opts['m'];
	else
		usage("invalid -m paramter passed");
  use_modules($tmodules, '-m parameter');
}

// Now let's make sure any required module provides are fullfilled.
if (isset($conf['requires']))
{
	foreach ($conf['requires'] as $require)
	{
		if (!isset($provides[$require]))
		{
      if (isset($defaults[$require]))
      {
        $modules[] = $defaults[$require];
      }
      else
      {
			  usage("missing a module providing required '$require' resource.");
      }
		}
	}
}

// If we made it this far, the initial consistency checks are done.

if (isset($opts['n']))
{
  $appid = preg_replace('/\W+/', '_', strtolower(trim($opts['n'])));
  $appns = explode('_', $appid);
  foreach ($appns as &$ns)
  {
    if ($ns != '')
    {
      $ns = ucfirst($ns);
    } 
  }
  $appns = join('', $appns);
}
else
{
  $appid = strtolower(basename($target));
  $appns = ucfirst($appid);
}

$tempns = $conf['appname'];
$tempid = strtolower($tempns);

$basedir = $tdir.'/'.$conf['base'];
if (!file_exists($basedir))
{
	invalid_settings();
}

mkdir($target, 0755, true);
system("rsync -a $basedir/ $target/");

foreach ($modules as $module)
{
  if (isset($module['path']))
  {
    $mdir = $tdir.'/'.$module['path'];
    system("rsync -a $mdir/ $target/");
  }
}

function rename_app_refs ($target_item)
{
  global $appid, $appns, $tempns, $tempid;
  $scmd = "xargs perl -pi -e \"s/$tempns/$appns/g; s/$tempid/$appid/g\"";
  if (is_link($target_item))
  {
    error_log("rename_app_refs shouldn't be used on links!");
    return;
  }
  if (is_dir($target_item))
  {
  	system("find $target_item -name '*.php' | $scmd");
  }
  elseif (is_file($target_item))
  {
    system("$scmd $target_item");
  }
}

// TODO: make the lib path configurable.
if (file_exists("$target/lib/$tempid"))
{
	rename("$target/lib/$tempid", "$target/lib/$appid");
  rename_app_refs("$target/lib/$appid");
}

if (isset($conf['bin']))
  $bins = $conf['bin'];
else
  $bins = [];

foreach ($modules as $module)
{
  if (isset($module['bin']))
  {
    $bins = array_merge($bins, $module['bin']);
  }
}

foreach ($bins as $bin)
{
  if (file_exists("$target/$bin"))
  {
    rename_app_refs("$target/$bin");
  }
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

if (isset($opts['j']))
{
	$jspath = $opts['j'];
	if (!file_exists($jspath))
	{
		error("'$jspath' does not exist, skipping Nano.js installation.");
	}

  if (isset($conf['nano.js']))
    $jsconf = $conf['nano.js'];
  else
    $jsconf = [];
  foreach ($modules as $module)
  {
    if (isset($module['nano.js']))
    {
      $jsconf += $module['nano.js'];
    }
  }

  if (isset($jsconf['scripts']))
  {
  	$scriptdest = $jsconf['scripts'];
  	put_tree("$jspath/scripts", "$target/$scriptdest", true);
  }

  if (isset($jsconf['styles']))
  {
  	$styledest  = $jsconf['styles'];
  	put_tree("$jspath/style",   "$target/$styledets",  true);
  }

	$dogrunt    = isset($jsconf['grunt'])   ? $jsconf['grunt']   : false;
  $dogulp     = isset($jsconf['gulp'])    ? $jsconf['gulp']    : false;
	$domods     = isset($jsconf['modules']) ? $jsconf['modules'] : false;

	if ($dogrunt)
	{
		put_file("$jspath/Gruntfile.js", "$target/Gruntfile.js");
		if (!file_exists("$target/grunt"))
		{
			mkdir("$target/grunt");
			system("rsync -a $jspath/grunt/ $target/grunt/");
      system("perl -pi -e 's/nano/$appid/g' $target/grunt/*");
		}
	}
  if ($dogulp)
  {
    if (!file_exists("$target/gulpfile.js"))
    {
      $gulp_ver = is_int($dogulp) ? $dogulp : DEFAULT_GULP;
      $gulp_src = "src/build/gulp$gulp_ver/gulpfile.js";
      if (file_exists("$target/$gulp_src"))
        copy("$target/$gulp_src", "$target/gulpfile.js");
      else
      {
        copy("$jspath/$gulp_src", "$target/gulpfile.js");
        system("perl -pi -e 's/nano/$appid/g' $target/gulpfile.js");
      }
    }
  }
	if ($domods)
	{
		put_tree("$jspath/node_modules", "$target/node_modules", true);
	}
}

// Now let's go into the target directory.
chdir($target);

// First run any module setup scripts.
foreach ($modules as $module)
{
  if (isset($module['setup']))
  {
    foreach ($module['setup'] as $script)
    {
      system("$script");
    }
  }
  if (isset($module['notify']))
  {
    echo $module['notify']."\n";
  }
}

// Now run the template setup scripts.
if (isset($conf['setup']))
{
  foreach ($conf['setup'] as $script)
  {
    system("$script");
  }
}
if (isset($conf['notify']))
{
  echo $conf['notify']."\n";
}

