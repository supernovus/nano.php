<?php

namespace Nano4\Loader;

/**
 * A Loader Provider Trait that looks for PHP files in a list of directories.
 */
trait Files
{ 
  public $dir;               // The directory which contains our classes.
  public $ext = '.php';      // The file extension for classes (.php)

  // If you are loading a class/instance you need to specify a naming pattern.
  public $class_naming = '%s';

  public function __construct ($opts=[])
  {
    $this->__construct_files($opts);
  }

  public function __construct_files ($opts=[])
  {
    if (isset($opts['dir']))
    {
      $this->dir = $opts['dir'];
    }
    if (isset($opts['ext']))
    {
      $this->ext = $opts['ext'];
    }
  }

  /** 
   * Return the filename associated with the given class.
   *
   * @param string $classname  The class name to look for.
   */
  public function file ($class)
  {
    if (isset($this->dir))
    {
      if (is_array($this->dir))
      { // Handle array dirs.
        $dirs = array();
        foreach ($this->dir as $dir)
        {
          $dirs[] = $dir . '/' . $class . $this->ext;
        }
        return $dirs;
      }
      return $this->dir . '/' . $class . $this->ext;
    }
    else
      return Null;
  }

  /** 
   * Does the given class exist?
   *
   * @param string $classname  The class name to look for.
   */
  public function is ($class)
  {
    if (isset($this->dir))
    {
      $file = $this->file($class);
      if (is_array($file))
      {
        foreach ($file as $seek)
        {
          if (file_exists($seek))
          {
            return True;
          }
        }
      }
      return file_exists($file);
    }
    return Null;
  }

  /** 
   * Find the file associated with a class.
   * Similar to is() but returns the first file that
   * exists, or Null if no possible matches were found.
   *
   * @param string $classname   The class name to look for.
   */
  public function find ($class)
  {
    if (isset($this->dir))
    {
      $file = $this->file($class);
      if (is_array($file))
      {
        foreach ($file as $seek)
        {
          if (file_exists($seek))
          {
            return $seek;
          }
        }
      }
      elseif (file_exists($file))
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
    $file = $this->find($class);
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
   * @param string $dirname   The name of the directory to add.
   */
  public function addDir ($dir, $top=False)
  {
    if (is_null($this->dir))
    {
      $this->dir = array();
    }
    elseif (!is_array($this->dir))
    {
      $this->dir = array($this->dir);
    }
    if ($top)
    {
      array_unshift($this->dir, $dir);
    }
    else
    {
      $this->dir[] = $dir;
    }
  }

  /**
   * Get a class id from an object instance.
   */
  public function class_id ($object)
  {
    $classname = strtolower(get_class($object));
    $type = str_replace('%s', '', strtolower($this->class_naming));
    $type = ltrim($type, "\\");
    return str_replace($type, '', $classname);
  }

}

