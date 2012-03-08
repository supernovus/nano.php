<?php

namespace Nano3\Plugins;
use Nano3\Exception;

/**
 * Nano3 Configuration Object
 *
 * Container portions based on Pimple
 * https://github.com/fabpot/Pimple/
 *
 * Supports loading full data from JSON, YAML or INI.
 *
 * Supports loading individual sections from JSON, YAML, INI or XML.
 *
 * @package Nano3\Conf
 * @author Timothy Totten
 */
class Conf implements \ArrayAccess
{
  protected $data;          // Our internal data structure.
  protected $autoload_dir;  // If specified, we will find config files in here.
  // A map of possible file extensions and associated types.
  protected $autoload_known = array
  (
    '.json' => 'json', 
    '.ini'  => 'ini', 
    '.yaml' => 'yaml',
    '.jsn'  => 'json',
    '.yml'  => 'yaml',
    '.url'  => 'confs',
    '.d'    => 'dir',
  );
  // If autoload_all is true, we actively scan the dir for known file-types
  // and load all of them up front. This is far more intensive than the
  // on-demand loading.
  protected $autoload_all = False;

  public $strict_mode = True;  // If true, we die on failure.

  /**
   * Load a JSON, YAML, or INI file and return an array
   * representing it. No direct XML support, sorry.
   * To load XML into a specific parameter, see loadXML() instead.
   *
   * @params string $filename  The file to load.
   * @params string $type      Force type. Otherwise auto-detect.
   */
  protected function load_file ($filename, $type=Null)
  {
    if (is_null($type))
    {
      if (preg_match("/\.jso?n$/i", $filename))
      {
        $type = 'json';
      }
      elseif (preg_match("/\.ini$/i", $filename))
      {
        $type = 'ini';
      }
      elseif (preg_match("/\.ya?ml$/i", $filename))
      {
        $type = 'yaml';
      }
      elseif (preg_match("/\.url$/i", $filename))
      {
        $type = 'confserv';
      }
      elseif (preg_match("/\.d$/i", $filename))
      {
        $type = 'dir';
      }
      else
      {
        throw new Exception('Could not auto-detect conf file type.');
      }
    }
    if ($type == 'json')
    {
      return json_decode(file_get_contents($filename), true);
    }
    elseif ($type == 'ini')
    {
      return parse_ini_file($filename, true);
    }
    elseif ($type == 'yaml')
    {
      return yaml_parse_file($filename);
    }
    elseif ($type == 'confserv')
    {
      $url  = file_get_contents($filename);
      $curl = new \Nano3\Utils\Curl();
      $json = $curl->get($url);
      return json_decode($json, true);
    }
    elseif ($type == 'dir')
    { // We create another config object, representing the nested
      // data structure.
      return new $this(array('dir'=>$filename));
    }
    throw new Exception('Invalid conf file type');
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

    if (isset($opts['dir']))
    {
      $this->autoload_dir = $opts['dir'];
    }

    if (isset($opts['data']) && is_array($opts['data']))
    {
      $this->setData($opts['data']);
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
    else
    {
      $this->data = array();
    }
  }

  /**
   * Set the configuration directory where we will
   * attempt autoloading config sections from.
   */
  public function setDir ($dir)
  {
    $this->autoload_dir = $dir;
  }

  /**
   * Load the data from an external file.
   *
   * @params string $filenname  The file to load data from.
   * @params string $type       If set, specifies the file type
   */
  public function loadFile ($filename, $type=Null)
  {
    $this->data = $this->load_file($filename, $type);
  }

  /**
   * Set the data from an array.
   *
   * @params array $data   The data to load.
   */
  public function setData ($data)
  {
    $this->data = $data;
  }

  /**
   * Set a parameter.
   *
   * @param string $id     The unique identifier for the parameter.
   * @param mixed  $value  THe value of the parameter.
   */
  public function offsetSet ($id, $value)
  {
    $this->data[$id] = $value;
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
    $this->data[$id] = $this->load_file($filename, $type);
  }

  /**
   * Attempt an autoload procedure.
   */
  public function autoload ($id)
  {
    foreach ($this->autoload_known as $ext => $type)
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
   * Load an XML file into a parameter, as a SimpleXML object.
   *
   * @param string $id         The unique identifier for the parameter.
   * @param string $filename   The XML file to load.
   */
  public function loadXML ($id, $filename)
  {
    $this->data[$id] = simplexml_load_file($filename);
  }

  /**
   * Get a SimpleXML parameter back as a DOM object.
   * Only works on SimpleXML objects.
   *
   * @param string $id   The identifier of the parameter.
   */
  public function getDOM ($id)
  {
    if (isset($this->data[$id]))
    {
      if ($this->data[$id] instanceof SimpleXMLElement)
      {
        return dom_import_simplexml($this->data[$id]);
      }
      throw new Exception('Attempt to getDOM on non XML attribute.');
    }
    throw new Exception("No such attribute, '$id', in Conf object.");
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
  }

  /**
   * getArray() -- an alias to to_array()
   */
  public function getArray ($id=Null)
  {
    return $this->to_array($id);
  }

  /**
   * to_json()
   *
   * Converts a section, or our entire data structure, into
   * a JSON string. This won't work properly if there are objects
   * without to_array() methods stored in the data section.
   *
   */
  public function to_json ($id=Null)
  {
    $array = $this->to_array($id);
    $json  = json_encode($array, true);
    return $json;
  }

  /**
   * getJSON() -- an alias to to_json()
   */
  public function getJSON ($id=Null)
  {
    return $this->to_json($id);
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

  /**
   * Unsets a parameter.
   *
   * @param string $id  The unique identifier of the parameter.
   */
  public function offsetUnset ($id)
  {
    unset($this->data[$id]);
  }

  // Aliases for object accessor interface.

  public function __set ($id, $value)
  {
    $this->offsetSet($id, $value);
  }

  public function __get ($id)
  {
    return $this->offsetGet($id);
  }

  public function __isset ($id)
  {
    return $this->offsetExists($id);
  }

  public function __unset ($id)
  {
    return $this->offsetUnset($id);
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
