#!/usr/bin/env php
<?php

// I will bump this any time the report schema changes in a non-compatible way.
const REPORT_VERSION = 4;

function err ($msg, $code=1)
{
  error_log($msg);
  exit($code);
}

if (PHP_SAPI !== 'cli')
{
  err("Script must be run from the command line.");
}

function usage ($msg="", $code=1)
{
  global $argv;
  if (trim($msg) != '') $msg .= "\n";
  $msg .= "usage: {$argv[0]} -d <dir> [-p <ext>] [-j <ext>] [-e <subpath>] [flags]";
  $msg .= "\n\n Parameters may be specified muliple times:";
  $msg .= "\n   -d specifies the directories to search, at least one is required.";
  $msg .= "\n   -p specifies the PHP extensions to search for.";
  $msg .= "\n   -j specifies the JS extensions to search for.";
  $msg .= "\n   -e specifies subpaths to exclude from searches.";
  $msg .= "\n At least one of -p or -j must be specified. No leading dot required.";
  $msg .= "\n\n Recognized flags:";
  $msg .= "\n   -C if used, we will change the files instead of just listing them.";
  $msg .= "\n   -Y if used, we'll use YAML output for the report.";
  $msg .= "\n   -J if used, we'll use JSON output for the report.";
  $msg .= "\n   -v each time used, increases the verbosity of the report.";
  $msg .= "\n      0 = Show files that were using Nano for each path.";
  $msg .= "\n      1 = Show which Nano calls were being used in each file.";
  $msg .= "\n      2 = Show files which didn't match but were scanned.";
  $msg .= "\n   -s Allow symbolic links to count as files to scan.";
  err($msg, $code);
}

function uniq ($arr)
{
  return array_values(array_unique($arr));
}

function add_uniq (&$arr1, $arr2)
{
  $pos = count($arr1);
  array_splice($arr1, $pos, 0, $arr2);
  $arr1 = uniq($arr1);
}

function rm_uniq (&$arr1, $arr2)
{
  foreach ($arr2 as $what)
  {
    $pos = array_search($what, $arr1);
    if ($pos !== false)
    {
      array_splice($arr1, $pos, 1);
    }
  }
}

const BOOT = 'BOOTSTRAP';

$opts = getopt('d:e:p:j:CYJvs');

if (!isset($opts['d']) || (!is_string($opts['d']) && !is_array($opts['d'])))
{
  usage();
}

$dirs = is_array($opts['d']) ? $opts['d'] : [$opts['d']];

$settings = new PathSettings($opts);

if (!$settings->isValid())
{
  usage();
}

$report = 
[
  'version' => REPORT_VERSION,
  'paths' => [],
];

foreach ($dirs as $dir)
{ // First build the primary report.
  if (is_string($dir))
  {
    $dir = trim($dir);
    if (file_exists($dir) && is_dir($dir))
    {
      $finder = new PathFindr($settings, $dir);
      $pathrep = $finder->getReport();
      $report['paths'][$dir] = $pathrep;
    }
  }
}

foreach ($report['paths'] as $dir => $pathrep)
{ // Next build the summaries.
  if (isset($pathrep['php']))
  {
    if (!isset($report['phpSummary']))
    {
      $report['phpSummary'] = 
      [
        'fileCount'  => 0,
        'allUses'    => [],
        'allDeps'    => [],
        'minUses'    => [],
        'bootstraps' => [],
      ];
    }

    $pathfiles = $pathrep['php']['files'];
    if ($settings->verbose > 1)
    { // We need to only include files that matched.
      foreach ($pathfiles as $fn => $fspec)
      {
        if ($fspec !== false)
        {
          $report['phpSummary']['fileCount']++;
        }
      }
    }
    else
    { // Only files to be modified are in the report.
      $report['phpSummary']['fileCount'] += count($pathrep['php']['files']);
    }

    $phpuses = $pathrep['php']['uses'];
    $phpdeps = $pathrep['php']['deps'];
    add_uniq($report['phpSummary']['allUses'], $phpuses);
    add_uniq($report['phpSummary']['allUses'], $phpdeps);
    add_uniq($report['phpSummary']['allDeps'], $phpdeps);

    $phpboots = $pathrep['php']['bootstraps'];
    add_uniq($report['phpSummary']['bootstraps'], $phpboots);
  }
  if (isset($pathrep['js']))
  {
    if (!isset($report['jsSummary']))
    {
      $report['jsSummary'] = 
      [
        'fileCount'  => 0,
      ];
    }
    $pathfiles = $pathrep['js']['files'];
    if ($settings->verbose > 1)
    { // We need to only include files that matched.
      foreach ($pathfiles as $fn => $fspec)
      {
        if ($fspec !== false)
        {
          $report['jsSummary']['fileCount']++;
        }
      }
    }
    else
    { // Only files to be modified are in the report.
      $report['jsSummary']['fileCount'] += count($pathrep['js']['files']);
    }
  }
}

// One final thing for the php summary.
if (isset($report['phpSummary']))
{
  add_uniq($report['phpSummary']['minUses'], $report['phpSummary']['allUses']);
  rm_uniq($report['phpSummary']['minUses'], $report['phpSummary']['allDeps']);
}

if (isset($opts['Y']))
{
  $format = 'Y';
}
elseif (isset($opts['J']))
{
  $format = 'J';
}
else
{
  // Attempt to auto-detect the output format.
  if (function_exists('yaml_emit'))
  {
    $format = 'Y';
  }
  elseif (function_exists('json_encode'))
  {
    $format = 'J';
  }
  else
  {
    $format = 'S';
  }
}

if ($format == 'Y')
{
  echo yaml_emit($report);
}
elseif ($format == 'J')
{
  echo json_encode($report, JSON_PRETTY_PRINT)."\n";
}
else
{
  error_log("Neither YAML or JSON extensions available? Fix your PHP!");
  echo serialize($report)."\n";
}

class PathSettings
{
  public $phpexts = [];
  public $jsexts  = [];
  public $exclude = [];
  public $change;
  public $verbose;
  public $symlink;

  public function __construct ($opts)
  {
    $this->add_from_opt($opts, 'p', 'add_php');
    $this->add_from_opt($opts, 'j', 'add_js');
    $this->add_from_opt($opts, 'e', 'add_exclude');
    $this->symlink = isset($opts['s']);
    $this->change = isset($opts['C']);
    $this->verbose = isset($opts['v']) 
      ? (is_array($opts['v']) ? count($opts['v']) : 1) 
      : 0;
  }

  protected function add_from_opt ($opts, $opt, $addFunc)
  {
    if (isset($opts[$opt]))
    {
      if (is_string($opts[$opt]))
      {
        $this->$addFunc($opts[$opt]);
      }
      elseif (is_array($opts[$opt]))
      {
        foreach ($opts[$opt] as $val)
        {
          if (is_string($val))
          {
            $this->$addFunc($val);
          }
        }
      }
    }
  }

  protected function add_php ($ext)
  {
    $this->phpexts[] = ltrim(trim($ext), '.');
  }

  protected function add_js ($ext)
  {
    $this->jsexts[] = ltrim(trim($ext), '.');
  }

  protected function add_exclude ($subpath)
  {
    $this->exclude[] = trim($subpath);
  }

  public function php_exts ()
  {
    if (count($this->phpexts) > 0)
    {
      return join('|', $this->phpexts);
    }
  }

  public function js_exts ()
  {
    if (count($this->jsexts) > 0)
    {
      return join('|', $this->jsexts);
    }
  }

  public function excludes ()
  {
    if (count($this->exclude) > 0)
    {
      return join('|', $this->exclude);
    }
  }

  public function isValid ()
  {
    return (count($this->phpexts) > 0 || count($this->jsexts) > 0);
  }

}

class PathFindr
{
  public $settings;
  public $phpfiles = [];
  public $jsfiles  = [];
  public $phpChanger;
  public $jsChanger;

  public function __construct ($settings, $path)
  {
    $this->settings = $settings;
    $pexts = $this->settings->php_exts();
    $jexts = $this->settings->js_exts();
    $excl  = $this->settings->excludes();
    $this->scan_path($path, $pexts, $jexts, $excl);
    if (isset($pexts))
    {
      $this->phpChanger = new PhpChangr($this);
    }
    if (isset($jexts))
    {
      $this->jsChanger = new JsChangr($this);
    }
  }

  public function getReport ()
  {
    $report = [];
    if (isset($this->phpChanger))
    {
      $report['php'] = $this->phpChanger->getReport();
    }
    if (isset($this->jsChanger))
    {
      $report['js'] = $this->jsChanger->getReport();
    }
    return $report;
  }

  protected function scan_path ($path, $pexts, $jexts, $excl)
  {
    if (file_exists($path) && is_dir($path))
    {
      $contents = scandir($path);
      foreach ($contents as $c)
      {
        if ($c == '.' || $c == '..') continue;
        if (isset($excl) && preg_match("{(?:$excl)}", $c))
        { // Path matched something in our exclude list.
          continue;
        }
        $fullpath = join(DIRECTORY_SEPARATOR, [$path, $c]);
        if (!$this->settings->symlink && is_link($fullpath))
        { // Skip symlinks.
          continue;
        }
        elseif ($this->settings->symlink && !file_exists($fullpath))
        { // Skip broken symlinks.
          continue;
        }
        elseif (is_dir($fullpath))
        { // A sub-directory, we'll scan it recursively.
          $this->scan_path($fullpath, $pexts, $jexts, $excl);
        }
        elseif (isset($pexts) && preg_match("{\.(?:$pexts)$}", $c))
        { // Matched our PHP extensions.
          $this->phpfiles[] = $fullpath;
        }
        elseif (isset($jexts) && preg_match("{\.(?:$jexts)$}", $c))
        { // Matched our JS extensions.
          $this->jsfiles[] = $fullpath;
        }
      }
    }
  }
}

abstract class PathChangr
{
  public $settings;
  public $finder;
  protected $matchedFiles = [];

  abstract protected function scanFiles ();

  public function __construct ($finder)
  {
    $this->finder = $finder;
    $this->settings = $finder->settings;
    $this->scanFiles();
  }

  public function getReport ()
  {
    $report = [];

    $v = $this->settings->verbose;
    if ($v > 0)
    {
      $report['files'] = $this->matchedFiles;
    }
    else
    {
      $report['files'] = array_keys($this->matchedFiles);
    }

    return $report;
  }
}

class lp
{
  const core = 'lum/lum-core';
  const fw   = 'lum/lum-framework';
  const arr  = 'lum/lum-arrays';
  const curl = 'lum/lum-curl';
  const curr = 'lum/lum-currency';
  const db   = 'lum/lum-db';
  const enc  = 'lum/lum-encode';
  const exp  = 'lum/lum-expression';
  const file = 'lum/lum-file';
  const html = 'lum/lum-html';
  const jrpc = 'lum/lum-json-rpc';
  const mail = 'lum/lum-mailer';
  const osrs = 'lum/lum-opensrs';
  const sock = 'lum/lum-socket';
  const spjs = 'lum/lum-spjs';
  const ss   = 'lum/lum-spreadsheet';
  const test = 'lum/lum-test';
  const text = 'lum/lum-text';
  const msgs = 'lum/lum-uimsg';
  const unit = 'lum/lum-units';
  const uuid = 'lum/lum-uuid';
  const ws   = 'lum/lum-webservice';
  const xml  = 'lum/lum-xml';
  const sdom = 'lum/simpledom';
  const riml = 'riml/riml-parser';

  const deps =
  [
    self::core => [self::file, self::enc, self::arr],
    self::fw   => [self::core, self::html, self::mail, self::msgs, self::db],
    self::curr => [self::curl, self::xml],
    self::db   => [self::core],
    self::html => [self::core, self::xml],
    self::jrpc => [self::uuid],
    self::osrs => [self::curl, self::sdom],
    self::msgs => [self::core],
    self::ws   => [self::core],
  ];
}

class PhpChangr extends PathChangr
{
  /**
   * A table of RegExps to search for, replacements values, and
   * tags representing which libraries they require.
   * A special tag represented by the BOOT constant is used on
   * bootstrap files that will likely need manual modifications even
   * after running this script.
   */
  const REPLACEMENT_TABLE =
  [
    [
      'require_once\\s+[\'"](?:\\.\\/)?lib/nano/init.php[\'"];',
      'require_once \'vendor/autoload.php\';',
      BOOT,
    ],
    [
      '\\\\Nano\\\\register\(\);', 
      '\\Lum\\Autoload::register();', 
      lp::core,
      BOOT
    ],
    [
      '(.*?)\s?=\s?\\\\Nano\\\\initialize\((.*?)\);', 
      "\\Lum\\Autoload::register();\n\\1 = \\Lum\\Core::getInstance(\\2);",
      lp::core,
      BOOT
    ],

    [
      '\\\\Nano\\\\get_instance\(\)',
      '\\Lum\\Core::getInstance()',
      lp::core,
    ],
    [
      '\\\\Nano\\\\get_php_content',
      '\\Lum\\Core::get_php_content',
      lp::core,
    ],
    [
      '\\\\Nano\\\\load_opts_from',
      '\\Lum\\Core::load_opts_from',
      lp::core,
    ],
    [
      '\\\\Nano\\\\Controllers',
      '\\Lum\\Controllers',
      lp::fw,
    ],
    [
      '\\\\Nano\\\\Models',
      '\\Lum\\Models',
      lp::fw,
    ],
    [
      '\\\\Nano\\\\DB',
      '\\Lum\\DB',
      lp::db,
    ],

    [
      '\\$nano->pragmas->json;',
      '\\$nano->output->json(true);',
    ],
    [
      '\\$nano->pragmas->xml;',
      '\\$nano->output->xml();',
    ],
    [
      '\\$nano->pragmas->xml_text;',
      '\\$nano->output->xml(true);',
    ],
    [
      '\\$nano->pragmas->no_cache;',
      '\\$nano->output->nocache(true);',
    ],
    [
      '\\$nano->pragmas->composer;',
      '',
    ],
    [
      '\\$nano->pragmas->simpledom;',
      '',
      lp::sdom,
    ],
    [
      '\\$nano->pragmas->getallheaders;',
      '',
    ],
    [
      '\\$nano->pragmas\[(.*?)\];',
      '\\0; throw new Exception("Nano pragmas in use, get rid of them!");',
    ],

    [
      '\\\\Nano\\\\Utils\\\\Arry',
      '\\Lum\\Arrays',
      lp::arr,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Base91',
      '\\Lum\\Encode\\Base91',
      lp::enc,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Browser',
      '\\Lum\\Plugins\\Client',
      lp::core,
    ],
    [
      '\\\\Nano\\\\Utils\\\\CSV',
      '\\Lum\\File\\CSV',
      lp::file,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Curl',
      '\\Lum\\Curl',
      lp::curl,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Currency\\\\OpenExchangeRates',
      '\\Lum\\Currency\\OpenExchangeRates',
      lp::curr,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Currency\\\\Google(?:.*?);',
      'throw new Exception("Google currency converter is dead.");',
      lp::curr,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Currency',
      '\\Lum\\Currency\\Format',
      lp::curr,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Expression',
      '\\Lum\\Expression',
      lp::exp,
    ],
    [
      '\\\\Nano\\\\Utils\\\\File',
      '\\Lum\\File',
      lp::file,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Flags::set_flag',
      '\\Lum\\Core::set_flag',
      lp::core,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Hash',
      '\\Lum\\Encode\\Hash',
      lp::enc,
    ],
    [
      '\\\\Nano\\\\Utils\\\\HTML\\\\',
      '\\Lum\\HTML\\',
      lp::html,
    ],
    [
      '\\\\Nano\\\\Utils\\\\HTML',
      '\\Lum\\HTML\\Helper',
      lp::html,
    ],
    [
      '\\\\Nano\\\\Utils\\\\JSONRPC',
      '\\Lum\\JSON\\RPC',
      lp::jrpc,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Language::accept',
      '\\Lum\\Plugins\\Client::acceptLanguage',
      lp::core,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Mailer',
      '\\Lum\\Mailer',
      lp::mail,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Notifications',
      '\\Lum\\UI\\Notifications',
      lp::msgs,
    ],
    [
      '\\\\Nano\\\\Utils\\\\OpenSRS\\\\',
      '\\Lum\\OpenSRS\\',
      lp::osrs,
    ],
    [
      '\\\\Nano\\\\Utils\\\\OpenSRS',
      '\\Lum\\OpenSRS\\Client',
      lp::osrs,
    ],
    [
      '\\\\Nano\\\\Utils\\\\SimpleAuth',
      '\\Lum\\Auth\\Simple',
      lp::fw,
    ],
    [
      '\\\\Nano\\\\Utils\\\\SocketDaemon',
      '\\Lum\\Socket\\Server',
      lp::sock,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Socket',
      '\\Lum\\Socket\\Client',
      lp::sock,
    ],
    [
      '\\\\Nano\\\\Utils\\\\SPJS',
      '\\Lum\\SPJS',
      lp::spjs,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Spreadsheet',
      '\\Lum\\Spreadsheet',
      lp::ss,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Text\\\\',
      '\\Lum\\Text\\',
      lp::text,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Text',
      '\\Lum\\Text\\Util',
      lp::text,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Translation',
      '\\Lum\\UI\\Strings',
      lp::msgs,
    ],
    [
      '\\\\Nano\\\\Utils\\\\UBJSON',
      '\\Lum\\Encode\\UBJSON',
      lp::enc,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Units',
      '\\Lum\\Units\\Units',
      lp::unit,
    ],
    [
      '\\\\Nano\\\\Utils\\\\UTF8XML',
      '\\Lum\\XML\\UTF8NCR',
      lp::xml,
    ],
    [
      '\\\\Nano\\\\Utils\\\\UUID',
      '\\Lum\\UUID',
      lp::uuid,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Webservice',
      '\\Lum\\Webservice',
      lp::ws,
    ],
    [
      '\\\\Nano\\\\Utils\\\\XML',
      '\\Lum\\XML\\Simple',
      lp::xml,
    ],
    [
      '\\\\Nano\\\\Utils\\\\Zip',
      '\\Lum\\File\\Zip',
      lp::file,
    ],

    [ // Any remaining references to Nano.
      '\\\\Nano',
      '\\Lum',
      lp::core,
    ],
    [ // Last but not least, we hope you're not using $core already...
      '\\$nano',
      '\\$core',
    ],
  ];

  protected function scanFiles ()
  {
    $v = $this->settings->verbose;
    $change = $this->settings->change;
    $files = $this->finder->phpfiles;
    foreach ($files as $file)
    {
      $changed = false;
      $content = file_get_contents($file);
      $filespec =
      [
        'matches'   => [],
        'uses'      => [],
        'bootstrap' => false,
      ];

      foreach (self::REPLACEMENT_TABLE as $tags)
      {
        $count = 0;
        $find = array_shift($tags);
        $rep  = array_shift($tags);
        if (preg_match_all("#$find#i", $content, $matches))
        {
          add_uniq($filespec['matches'], $matches[0]);
          if (count($tags) > 0)
          {
            foreach ($tags as $tag)
            {
              if ($tag == BOOT)
              {
                $filespec['bootstrap'] = true;
              }
              elseif (!in_array($tag, $filespec['uses']))
              {
                $filespec['uses'][] = $tag;
              }
            }
          }
        }
        if ($change)
        {
          if (is_string($rep))
          {
            $content = preg_replace("#$find#i", $rep, $content, -1, $count);
          }
          elseif (is_callable($rep))
          {
            $content = preg_replace_callback("#$find#i", $rep, $content, -1, $count);
          }
          else
          { // This should not happen.
            error_log("Replacement value was not string or callable");
            $count = 0; // sanity check.
          }
          if ($count > 0)
          {
            $changed = true;
          }
        }
      }

      if (count($filespec['matches']) > 0)
      { // We found some matches.
        $this->matchedFiles[$file] = $filespec;
      }
      elseif ($v > 1)
      { // No matches.
        $this->matchedFiles[$file] = false;
      }

      if ($change && $changed)
      {
        file_put_contents($file, $content);
      }
    }
  }

  public function getReport ()
  {
    $report = parent::getReport();
    $uses = [];
    $deps = [];
    $boots = [];
    foreach ($this->matchedFiles as $file => $spec)
    {
      if (is_array($spec)) 
      {
        if (isset($spec['uses']) && count($spec['uses']) > 0)
        {
          add_uniq($uses, $spec['uses']);
          foreach ($spec['uses'] as $libname)
          {
            if (isset(lp::deps[$libname]))
            {
              add_uniq($deps, lp::deps[$libname]);
            }
          }
        }
        if (isset($spec['bootstrap']) && $spec['bootstrap'])
        {
          if (!in_array($file, $boots))
          {
            $boots[] = $file;
          }
        }
      }
    }
    if (count($deps) > 0)
    {
      rm_uniq($uses, $deps);
    }
    $report['uses'] = $uses;
    $report['deps'] = $deps;
    $report['bootstraps'] = $boots;
    return $report;
  }
}

class JsChangr extends PathChangr
{
  protected function scanFiles ()
  {
    $v = $this->settings->verbose;
    $change = $this->settings->change;
    $files = $this->finder->jsfiles;
    foreach ($files as $file)
    {
      $content = file_get_contents($file);
      if (preg_match_all('#(\w+\.)?Nano(\.\w+)?#', $content, $matches))
      {
        $this->matchedFiles[$file]['matches'] = uniq($matches[0]);
      }
      elseif ($v > 1)
      { // List unmatched files too when verbosity is 2 or higher.
        $this->matchedFiles[$file] = false;
      }
      if ($change)
      { // The change method is super simple for Lum.js
        $newcontent = str_replace('Nano', 'Lum', $content);
        if ($newcontent != $content)
        {
          file_put_contents($file, $newcontent);
        }
      }
    }
  }
}
