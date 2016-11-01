<?php

namespace Nano\Loader;

/**
 * A Loader Provider Trait that looks for PHP files in a list of directories.
 */
trait Files
{ 
  public $dirs = [];         // The directory which contains our classes.
  public $ext = '.php';      // The file extension for classes (.php)

  // If you are loading a class/instance you need to specify a naming pattern.
  public $class_naming = '%s';

  public function __construct ($opts=[])
  {
    $this->__construct_files($opts);
  }

  public function __construct_files ($opts=[])
  {
    if (isset($opts['dirs']))
    {
      $this->dirs = $opts['dirs'];
    }
    if (isset($opts['ext']))
    {
      $this->ext = $opts['ext'];
    }
  }

  /** 
   * Does the given file exist?
   *
   * @param string $filename  The class name to look for.
   */
  public function is ($filename)
  {
    $file = $this->find_file($filename);
    return isset($file);
  }

  /** 
   * Find the file associated with a class.
   * Similar to is() but returns the first file that
   * exists, or Null if no possible matches were found.
   *
   * @param string $classname   The class name to look for.
   */
  public function find_file ($filename)
  {
    foreach ($this->dirs as $dir)
    {
      $file = $dir . '/' . $filename . $this->ext;
      if (file_exists($file))
      {
        return $file;
      }
    }
  }

  /**
   * Get a class name for a file.
   * This performs a require_once on the file as well.
   */
  public function find_class ($class)
  {
    $file = $this->find_file($class);
    if ($file)
    {
      require_once($file);
      $classname = sprintf($this->class_naming, $class);
      if (class_exists($classname))
      {
        return $classname;
      }
    }
  }

  /** 
   * Add a directory to search through.
   *
   * @param string $dir   The name of the directory to add.
   */
  public function addDir ($dir, $top=False)
  {
    if ($top)
    {
      if (is_array($dir))
        array_splice($this->dirs, 0, 0, $dir);
      else
        array_unshift($this->dirs, $dir);
    }
    else
    {
      if (is_array($dir))
        array_splice($this->dirs, -1, 0, $dir);
      else
        $this->dirs[] = $dir;
    }
  }

  /**
   * Get a class id from an object instance.
   * This is not generally recommended for use anymore, and is not
   * guaranteed to exist in other Loader classes.
   */
  public function class_id ($object)
  {
    $classname = strtolower(get_class($object));
    $type = str_replace('%s', '', strtolower($this->class_naming));
    $type = ltrim($type, "\\");
    return str_replace($type, '', $classname);
  }

}

