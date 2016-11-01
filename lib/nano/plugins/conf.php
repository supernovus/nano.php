<?php

namespace Nano\Plugins;
use Nano\Exception;

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
      $diropts = array('dir'=>$filename);
      if (file_exists("$filename/autoload"))
      {
        $diropts['scan'] = True;
      }
      return new $this($diropts);
    }
    else
    { // Assume the type is handled by the Data Object library.q
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
    if (isset($opts['strict']))
    {
      $this->strict_mode = $opts['strict'];
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
   * files using a special include statement.
   */
  protected function load_file_chain ($filename, $type=Null)
  {
    $data = $this->load_file($filename, $type);

    if (is_array($data) && isset($data['@include']))
    {
      $infiles = $data['@include'];
      if (!is_array($infiles))
      {
        $infiles = [$infiles];
      }
      unset($data['@include']);

      foreach ($infiles as $infile)
      {
        $include = Null;
        if (isset($this->autoload_dir) && strpos($infile, '.') === FALSE)
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
    return $data;
  }

  /**
   * Load the data from an external file.
   *
   * @params string $filenname  The file to load data from.
   * @params string $type       If set, specifies the file type
   */
  public function loadFile ($filename, $type=Null)
  {
    $this->data = $this->load_file_chain($filename, $type);
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

  // A query. Return a section of data based on a query.
  public function getPath ($path)
  {
    $paths = explode('/', trim($path, '/'));
    $section = $this;
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

  /**
   * Returns a closure that stores the result of the closure
   * for uniqueness in the scope of the Conf object.
   *
   * @param Closure $callable  A closure to wrap for uniqueness.
   *
   * @return Closure The wrapped closure.
   */
  public function share (Closure $callable)
  {
    return function ($c) use ($callable)
    {
      static $object;
      if (is_null($object))
      {
        $object = $callable($c);
      }
      return $object;
    };
  }

  /**
   * Protect a callable from being interpreted as a service.
   * This lets you store a callable as a parameter.
   *
   * @param Closure $callable  A closure to protect.
   *
   * @return Closure  The protected closure.
   */
  function protect(Closure $callable)
  {
    return function ($c) use ($callable)
    {
      return $callable;
    };
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
