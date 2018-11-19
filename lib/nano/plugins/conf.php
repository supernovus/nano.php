<?php

namespace Nano\Plugins;
use Nano\Exception;
use Nano\Utils\Arry;

/**
 * Nano Configuration Object
 *
 * Container portions based on Pimple
 * https://github.com/fabpot/Pimple/
 *
 * It's now based on the Data Objects framework,
 * and extends the Arrayish base class.
 *
 * It can load JSON, YAML, INI, nested subdirectories,
 * and ConfServ URLs. It supports on-demand autoloading,
 * and 'scan directory' autoloading.
 *
 * @package Nano\Conf
 * @author Timothy Totten
 */
class Conf extends \Nano\Data\Arrayish
{
  protected $data;          // Our internal data structure.
  protected $autoload_dir;  // If specified, we will find config files in here.
  // If autoload_scan is true, we actively scan the dir for known file-types
  // and load all of them up front. This is far more intensive than the
  // on-demand loading, but necessary in some situations.
  protected $autoload_scan = False;

  // A map of possible file extensions and associated types.
  // Used in the on-demand autoloading.
  protected $_file_type_extensions = array
  (
    '.json' => 'json', 
    '.ini'  => 'ini', 
    '.yaml' => 'yaml',
    '.jsn'  => 'json',
    '.yml'  => 'yaml',
    '.url'  => 'confs',
    '.d'    => 'dir',
    '.txt'  => 'scalar',
  );
  // A map of filename matches to determine type.
  // Used in load_file() and in autoload scanning.
  protected $_file_type_matches = array
  (
    'json'   => '/\.jso?n$/i',
    'ini'    => '/\.ini$/i',
    'yaml'   => '/\.ya?ml/i',
    'dir'    => '/\.d$/i',
    'confs'  => '/\.url$/i',
    'scalar' => '/\.txt$/i',
  );

  protected $_file_type_include_pos = 6; // Keep the first x file type exts.

  public $strict_mode = false;  // If true, we die on failure.

  // The following are used in getPath() calls.
  public $path_sep = '/'; 
  public $parent_ref = '../'; 
  public $root_ref = '//';

  // The maximum depth we'll search for '@use' statements.
  // You'll generally want this to be higher than 0 if using '@use' statements.
  public $max_use_depth = 0;

  // We can create short aliases to traits.
  protected $known_traits = [];

  public function dir ()
  {
    return $this->autoload_dir;
  }

  /**
   * Pass in a filename, and find out if it is a supported type.
   */
  protected function get_type ($filename)
  {
    $type = Null;
    foreach ($this->_file_type_matches as $ftype => $match)
    {
      if (preg_match($match, $filename))
      {
        $type = $ftype;
        break;
      }
    }
    return $type;
  }

  /**
   * Load a JSON, YAML, or INI file and return an array
   * representing it. No direct XML support, sorry.
   * To load XML into a specific parameter, see loadXML() instead.
   *
   * @params string $filename  The file to load.
   * @params string $type      Optional. Specify the file type.
   */
  protected function load_file ($filename, $type=Null)
  {
#    error_log("loadfile($filename)");
    if (is_null($type))
    { // Let's see if we can detect the file type.
      $type = $this->get_type($filename);
      // If we still didn't find the type, it's time to die.
      if (is_null($type))
      {
        throw new Exception('Could not auto-detect conf file type.');
      }
    }

    if ($type == 'ini')
    {
      return parse_ini_file($filename, true);
    }
    elseif ($type == 'scalar')
    {
      return file_get_contents($filename);
    }
    elseif ($type == 'confs')
    {
      $url  = file_get_contents($filename);
      $curl = new \Nano\Utils\Curl();
      $config = $curl->get($url);
      return $this->load_data($config);
    }
    elseif ($type == 'dir')
    { // We create another config object, representing the nested
      // data structure.
      $diropts = 
      [
        'parent'   => $this,
        'useDepth' => $this->max_use_depth,
        'strict'   => $this->strict,
        'dir'      => $filename,
      ];
      if (file_exists("$filename/autoload"))
      {
        $diropts['scan'] = True;
      }
      return new $this($diropts);
    }
    else
    { // Assume the type is handled by the Data Object library.
#      error_log("Going to load '$filename' as '$type'");
      $text = file_get_contents($filename);
#      error_log("Text: $text");
      $data = $this->load_data($text, array('type'=>$type));
#      error_log("Data: ".json_encode($data));
      return $data;
    }
  }

  /**
   * Initialize the container.
   *
   * @param array $opts The options to initialize the data.
   */
  public function __construct ($opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }

    if (isset($opts['strict']))
    {
      $this->strict_mode = $opts['strict'];
    }

    if (isset($opts['useDepth']))
    {
      $this->max_use_depth = $opts['useDepth'];
    }

    if (isset($opts['scan']))
    {
      $this->autoload_scan = $opts['scan'];
    }

    if (isset($opts['data']))
    {
      $this->load($opts['data']);
    }
    elseif (isset($opts['file']) && file_exists($opts['file']))
    {
      if (isset($opts['type']))
      {
        $type = $opts['type'];
      }
      else
      {
        $type = Null;
      }
      $this->loadFile($opts['file'], $type);
    }
    elseif (isset($opts['dir']))
    {
      $this->setDir($opts['dir']);
    }
    else
    {
      $this->data = array();
    }
  }

  /**
   * Set the configuration directory where we will
   * attempt autoloading config sections from.
   */
  public function setDir ($dir, $scan=Null)
  {
    $this->autoload_dir = $dir;
    if (!isset($this->data))
    {
      $this->data = array();
    }
    if (is_null($scan))
    {
      $scan = $this->autoload_scan;
    }
    if ($scan)
    {
      $this->scanDir();
    }
  }

  /**
   * Scan our autoload directory for all known files.
   */
  public function scanDir ()
  {
    if (isset($this->autoload_dir))
    {
      $dir = $this->autoload_dir;
      foreach (scandir($dir) as $file)
      {
        $type = $this->get_type($file);
        if (isset($type))
        {
          $matches = $this->_file_type_matches;
          $basename = preg_replace($matches[$type], '', $file);
          $conffile = $dir . '/' . $file;
          $this->loadInto($basename, $conffile, $type);
        }
      }
    }
  }

  /**
   * A wrapper for load_file() that supports chaining configuration
   * files using a special '@include' statement.
   */
  protected function load_file_chain ($filename, $type=Null)
  {
    $data = $this->load_file($filename, $type);

    if (is_array($data) && isset($data['@include']))
    { // Include more files.
      $infiles = $data['@include'];
      if (!is_array($infiles))
      {
        $infiles = [$infiles];
      }
      unset($data['@include']);

      foreach ($infiles as $infile)
      {
        $include = Null;
        if (isset($this->autoload_dir) && !file_exists($infile))
        {
          $exts = $this->_file_type_extensions;
          $pos  = $this->_file_type_include_pos;
          $exts = array_slice($exts, 0, $pos);
          foreach ($exts as $ext => $type)
          {
            $tryfile = $this->autoload_dir . '/' . $infile . $ext;
            if (file_exists($tryfile))
            {
              $include = $this->load_file_chain($tryfile, $type);
              break;
            }
          }
        }
        else
        {
          $include = $this->load_file_chain($infile);
        }

        if (isset($include) && is_array($include))
        {
          $data += $include;
        }
      }
    }

    if (is_array($data) && isset($data['@useDepth']))
    { // A top-level configuration option to change '@use' depth.
      $this->max_use_depth = intval($data['@useDepth']);
      unset($data['@useDepth']);
    }

    if (is_array($data) && isset($data['@traits']) && Arry::is_assoc($data['@traits']))
    {
      $this->known_traits = $data['@traits'];
    }

    return $data;
  }

  /**
   * Check for "@use" statements, and parse them.
   */
  protected function parse_use (&$data, $level=0)
  {
    if (isset($data['@use']))
    { // Traits, allow you to reuse common sets of properties.
      $traits = $data['@use'];
#      error_log("parsing '@use' ($level) statement: ".json_encode($traits));
      if (!is_array($traits))
      {
        $traits = [$traits];
      }
      unset($data['@use']);

      $pathopts = [];

      $overrides =
      [
        '@pathSeparator'   => 'pathSep',
        '@parentReference' => 'parentRef',
        '@rootReference'   => 'rootRef',
      ];
      foreach ($overrides as $dname => $oname)
      {
        if (isset($data[$dname]))
        {
          $pathopts[$oname] = $data[$dname];
          unset($data[$dname]);
        }
      }

      foreach ($traits as $trait)
      {
        if (isset($this->known_traits[$trait]))
        { // A registered trait, look it up.
          $tname = $this->known_traits[$trait];
          if (is_string($tname))
          {
            $tdata = $this->getPath($tname, $pathopts);
            $this->known_traits[$trait] = $tdata;
          }
          elseif (is_array($tname))
          {
            $tdata = $tname;
          }
        }
        else
        { // Look it up directly.
          $tdata = $this->getPath($trait, $pathopts);
        }
#        error_log("Looked up '$trait' and found: ".json_encode($tdata));
        if (isset($tdata))
        {
          if (Arry::is_assoc($tdata))
          { // An associative array, let's add it.
            $data += $tdata;
          }
          elseif (is_object($tdata) && $tdata instanceof Conf)
          { // Another Conf object, recurse it's data.
            foreach ($tdata as $tkey => $tval)
            {
              if (!isset($data[$tkey]))
              {
                $data[$tkey] = $tval;
              }
            }
          }
        }
      }
    }

    // If we're on the final recurse depth, we're done.
    if ($level == $this->max_use_depth) return;

    // Increment the level.
    $level++;

    // Recurse further down the tree.
    $keys = array_keys($data);
#    error_log("Going to scan ".json_encode($keys)." for '@use' statements.");
    foreach ($keys as $dkey)
    {
      $dval = &$data[$dkey];
#      error_log("Checking '$dkey' for '@use' statements.");
      if (Arry::is_assoc($dval))
      { // Call parse_use on the nested data structure.
        $this->parse_use($dval, $level);
      }
    }
  }

  /**
   * Load the data from an external file.
   *
   * @param string $filenname  The file to load data from.
   * @param string $type       If set, specifies the file type
   */
  public function loadFile ($filename, $type=Null)
  {
    $this->data = $this->load_file_chain($filename, $type);
    if (Arry::is_assoc($this->data))
    {
      $this->parse_use($this->data);
    }
  }

  /**
   * Set a parameter to be the contents of a config file.
   *
   * @param string $id        The unique identifier for the parameter.
   * @param string $filename  The filename to the parameter.
   * @param string $type      If set, force the type [json|yaml|ini]
   */
  public function loadInto ($id, $filename, $type=Null)
  {
#    error_log("Loading '$filename' into '$id', as '$type'");
    $data = $this->load_file_chain($filename, $type);
#    error_log("Loaded: ".json_encode($data));
    $this->data[$id] = $data;
    if (Arry::is_assoc($data))
    {
      $this->parse_use($this->data[$id]);
    }
  }

  /**
   * Attempt an autoload procedure.
   */
  public function autoload ($id)
  {
    foreach ($this->_file_type_extensions as $ext => $type)
    {
      $conffile = $this->autoload_dir . '/' . $id . $ext;
      if (file_exists($conffile))
      {
        $this->loadInto($id, $conffile, $type);
        return True;
      }
    }
    return False;
  }

  /**
   * to_array()
   *
   * Just in case the data you're querying isn't
   * really an array, but an object, we convert it.
   *
   */
  public function to_array ($id=Null)
  {
    if (!isset($id))
    { // Return an array representing all of our config sections.
      $array = array();
      foreach ($this as $key => $val)
      {
        $array[$key] = $this->to_array($key);
      }
      return $array;
    }

    // Okay, get the item.
    $data = $this[$id];

    if (is_scalar($data))
      return $data;

    if (is_array($data))
    { // Parse each array element.
      $array = array();
      foreach ($data as $key => $val)
      {
        if (is_object($val) && is_callable(array($val, 'to_array')))
        { // Deparse this.
          $array[$key] = $val->to_array();
        }
        else
        { // Return as is.
          $array[$key] = $val;
        }
      }
    }
    elseif (is_object($data) && is_callable(array($data, 'to_array')))
    { // Use toArray on the data itself.
      $array = $data->to_array();
    }
    return $array;
  }

  /**
   * Gets a parameter if it exists.
   *
   * @param string $id The unique identifier for the parameter.
   *
   * @return mixed  The value of the parameter, or Null if no such param.
   *
   * @throws  InvalidArgumentException if the identifier is not defined.
   */
  public function offsetGet ($id)
  {
    if (!array_key_exists($id, $this->data))
    {
      if (isset($this->autoload_dir) && $this->autoload($id))
      { // We were able to autoload a config file, call ourselves again.
        return $this->offsetGet($id);
      }
      elseif ($this->strict_mode)
      {
        throw new \InvalidArgumentException(
          sprintf('Identifier "%s" is not defined.', $id));
      }
      else
      {
        return Null;
      }
    }
    return $this->data[$id] instanceof Closure 
      ? $this->data[$id]($this)
      : $this->data[$id];
  }

  /**
   * Checks to see if a parameter is set.
   *
   * @param string $id   The unique identifier of the parameter.
   *
   * @return Boolean
   */
  public function offsetExists ($id) 
  {
    if (!array_key_exists($id, $this->data) && isset($this->autoload_dir)
      && $this->autoload($id))
    { // Call ourself again.
      return $this->offsetExists($id);
    }
    return isset($this->data[$id]);
  }

  public function array_keys ()
  {
    return array_keys($this->data);
  }

  /**
   * Get a section of data based on a path string.
   *
   * @param str $path    The path we are retreiving.
   * @param array $opts  Options to pass to parse_paths().
   */
  public function getPath ($path, $opts=[])
  {
    if (isset($opts, $opts['paths'], $opts['section']))
    { // It's the already parsed pathSpec.
      $pathSpec = $opts;
    }
    else
    { // Parse the options.
      $pathSpec = $this->parse_paths($path, $opts);
    }

    $paths = $pathSpec['paths'];
    $section = $pathSpec['section'];
    foreach ($paths as $path)
    {
      if (isset($section[$path]))
      {
        $section = $section[$path];
      }
      else
      {
        return Null;
      }
    }
    return $section;
  }

  protected function parse_paths ($path, $opts=[])
  {
    $psep = isset($opts['pathSep'])   ? $opts['pathSep']   : $this->path_sep;
    $pref = isset($opts['parentRef']) ? $opts['parentRef'] : $this->parent_ref;
    $rref = isset($opts['rootRef'])   ? $opts['rootRef']   : $this->root_ref;
    $psc  = strlen($psep);
    $prc  = strlen($pref);
    $rrc  = strlen($rref);

    $hasParent = (isset($this->parent) && $this->parent instanceof Conf);

    $section = $this;

    if (substr($path, 0, $rrc) == $rref)
    { // Root path.
      $path = substr($path, $rrc);
      if ($hasParent)
      { // Find the parent Conf object.
        while($parent = $section->parent())
        {
          if ($parent instanceof Conf)
          { 
            $section = $parent;
          }
          else
          {
            break;
          }
        }
      }
    }
    elseif (substr($path, 0, $prc) == $pref)
    { // A parent path, there might be multiple.
      while (substr($path, 0, $prc) == $pref)
      {
        $path = substr($path, $prc);
        if ($hasParent)
        {
          $parent = $section->parent();
          if (isset($parent) && $parent instanceof Conf)
          {
            $section = $parent;
          }
          else
          {
            $hasParent = false;
          }
        }
      }
    }

    $paths = explode($psep, trim($path, $psep));

    $retval =
    [
      'paths'        => $paths,
      'section'      => $section,
      'pathSep'      => $psep,
      'parentRef'    => $pref,
      'rootRef'      => $rref,
      'pathSepLen'   => $psc,
      'parentRefLen' => $prc,
      'rootRefLen'   => $rrc,
    ];
    return $retval;
  }

  /**
   * Get a section of data based on a path string, and convert it
   * to a PHP array and/or scalar value.
   *
   * @param str $path    The path we are retreiving.
   * @param bool $full   If true, return a full array, otherwise return keys.
   * @param array $opts  Options to pass to getPath().
   *
   */
  public function getPathValues ($path, $full=False, $opts=[])
  {
    $section = $this->getPath($path, $opts);
    if ($full)
    {
      if (is_object($section))
      {
        if (is_callable([$section, 'to_array']))
        {
          return $section->to_array();
        }
        else
        {
          return Null;
        }
      }
      else
      {
        return $section;
      }
    }
    else
    { // Get a summary, consisting of the keys.
      $keys = Null;
      if (is_object($section))
      {
        if (is_callable([$section, 'scanDir']))
        { // Pre-load all known entities.
          $section->scanDir();
        }
        if (is_callable([$section, 'array_keys']))
        {
          $keys = $section->array_keys();
        }
        else
        {
          return Null;
        }
      }
      elseif (is_array($section))
      {
        $keys = array_keys($section);
      }
      elseif (is_scalar($section))
      {
        return $section;
      }
      return $keys;
    }
  }
  
  /**
   * Gets the raw parameters.
   *
   * @param string $id   The unique identifier.
   *
   * @return mixed  The alue of the parameter.
   *
   * @throws  InvalidArgumentException if the identifier is not defined.
   */
  function raw ($id)
  {
    if (!array_key_exists($id, $this->data))
    {
      if ($this->strict_mode)
      {
        throw new \InvalidArgumentException(
          sprintf('Indentifier "%s" is not defined.', $id));
      }
      else
      {
        return Null;
      }
    }
    return $this->data[$id];
  }

}

// End of library.
