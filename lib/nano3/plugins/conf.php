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
  );

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
