<?php

/* Data\Object -- Base class for all Nano3\Base\Data classes.
 *
 * These are "magic" objects which are meant for converting data between
 * different formats easily, with PHP arrays, JSON and XML as the default
 * targets.
 *
 * The load() method, which can be used in the constructor, will determine
 * the data type either by a 'type' parameter passed to it, or by calling
 * the detect_data_type() method (a default version is supplied, feel free
 * to override it, or create chains using parent:: calls.)
 * when the type has been determined, the data will be passed to a method
 * called load_$type() which will be used to load the data.
 *
 * It is expected that custom methods to perform operations on the data
 * will be added, as well as operations to return the data in specific
 * formats (typically the same ones that you accept in the load() statement.)
 *
 * The default version can load PHP arrays, plus JSON and YAML strings.
 * It also has to_array(), to_json() and to_yaml() methods to return in 
 * those formats. The JSON and YAML methods wrap around the array ones,
 * so overriding the array methods is all you really need to do.
 * The default versions perform no transformations, but simply set our
 * data to the PHP array result.
 *
 * This will also detect SimpleXML and DOM objects, and XML strings.
 * In order to load any of the above objects, you need to implement
 * the load_simple_xml() method (XML strings and DOM objects will be
 * converted to SimpleXMLElement objects and passed through.)
 *
 * In order to use the to_dom_document(), to_dom_element() or to_xml() methods
 * you must implement a to_simple_xml() method first (again for simplicity
 * we call to_simple_xml() then convert the object it return to the desired
 * format.)
 *
 * Add extra formats as desired, chaining the detect_data_type() and 
 * detect_string_type() methods is easy, so go crazy!
 *
 */

namespace Nano3\Base\Data;

abstract class Object
{
  protected $data = array();   // The actual data we represent.
  protected $parent;           // Will be set if we have a parent object.

  public function __construct ($mixed=Null, $opts=array())
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (method_exists($this, 'data_init'))
    { // The data_init can set up pre-requisites to loading our data.
      // It CANNOT reference our data, as that has not been loaded yet.
      $this->data_init($opts);
    }

    // How we proceed depends on if we have initial data.
    if (isset($mixed))
    { // Load the passed data.
      $loadopts = array('clear'=>False, 'prep'=>True, 'post'=>True);
      if (isset($opts['type']))
      {
        $loadopts['type'] = $opts['type'];
      }
      $this->load($mixed, $loadopts);
    }
    elseif (is_callable(array($this, 'data_defaults')))
    { 
      if (!isset($opts['nodefaults']) || !$opts['nodefaults'])
      {
        // Set our default values.
        $this->data_defaults($opts);
      }
    }
  }

  // Return the parent object.
  public function parent ()
  {
    return $this->parent;
  }

  // Returns the converted data structure.
  public function load_data ($data, $opts=array())
  {
    $return = Null;
    // If we set the 'prep' option, send the data to data_prep()
    // for initial preparations which will return the prepared data.
    if (isset($opts['prep']) && $opts['prep'] 
      && method_exists($this, 'data_prep'))
    {
      $data = $this->data_prep($data, $opts);
    }
    // Figure out the data type.
    $type = Null;
    if (isset($opts['type']))
    {
      $type = $opts['type'];
    }
    else 
    {
      $type = $this->detect_data_type($data);
    }
    // Handle the data type.
    if (isset($type))
    {
      $method = "load_$type";
      if (method_exists($this, $method))
      {
#        error_log("Sending '$data' to '$method'");
        // If this method returns False, something went wrong.
        // If it returns an array or object, that becomes our data.
        // If it returns Null or True, we assume the method set the data.
        $return = $this->$method($data, $opts);
#        error_log("Retreived: ".json_encode($return));
        if ($return === False)
        {
          throw new Exception("Could not load data.");
        }
      }
      else
      {
        throw new Exception("Could not handle data type.");
      }
    }
    else
    {
      throw new Exception("Unsupported data type.");
    }
    return $return;
  }

  // Set our data to the desired structure.
  public function load ($data, $opts=array())
  { // If we set the 'clear' option, clear our any existing data.
    if (isset($opts['clear']) && $opts['clear'])
    {
      $this->clear();
    }
    $return = $this->load_data($data, $opts);
    if (isset($return) && $return !== True)
    {
      $this->data = $return;
    }
    // If we have set the 'post' option, call data_post().
    if (isset($opts['post']) && $opts['post'] 
      && method_exists($this, 'data_post'))
    {
      $this->data_post($opts);
    }
  }

  // Clear our data.
  public function clear ($opts=array())
  {
    $this->data = array();
  }

  // Spawn a new empty data object.
  public function spawn ($opts=array())
  {
    $copy = clone $this;
    $copy->clear();
    return $copy;
  }

  // Default version of detect_data_type().
  // Feel free to override it, and even call it using parent.
  protected function detect_data_type ($data)
  {
    if (is_array($data))
    {
      return 'array';
    }
    elseif (is_string($data))
    {
      return $this->detect_string_type($data);
    }
    elseif (is_object($data))
    {
      if ($data instanceof \SimpleXMLElement)
      {
        return 'simple_xml';
      }
      elseif ($data instanceof \DOMNode)
      {
        return 'dom_node';
      }
    }
  }

  // Detect the type of string being loaded.
  // This is very simplistic, you may want to override it.
  // It currently supports JSON strings starting with { and [
  // and XML strings starting with <. You'll need to implement
  // a load_xml_string() method if you want XML strings to work.
  protected function detect_string_type ($string)
  {
    $fc = substr(trim($string), 0, 1);
    if ($fc == '<')
    { // XML detected.
      return 'xml_string';
    }
    elseif ($fc == '[' || $fc == '{')
    { // JSON detected.
      return 'json';
    }
  }

  // This is very (VERY) cheap. Override as needed.
  public function load_array ($array, $opts=Null)
  {
    return $array;
  }

  // Again, pretty cheap, but works well.
  public function load_json ($json, $opts=Null)
  {
    $array = json_decode($json, True);
    return $this->load_array($array, $opts);
  }

  // Just as cheap, different format.
  public function load_yaml($yaml, $opts=Null)
  {
    $array = yaml_parse($yaml);
    return $this->load_array($array, $opts);
  }

  // Output as an array. Just as cheap as load_array().
  public function to_array ($opts=Null)
  {
    return $this->data;
  }

  // Output as a JSON string. Again, pretty cheap.
  public function to_json ($opts=Null)
  {
    return json_encode($this->to_array($opts));
  }

  // And again, the same as above, but with YAML.
  public function to_yaml ($opts=Null)
  {
    return yaml_emit($this->to_array($opts));
  }

  /** 
   * All of the XML-related methods require that you implement the
   * load_simple_xml() and to_simple_xml() methods.
   */

  // A protected method that can be used by your to_simple_xml()
  // methods to decide how to create the root element.
  // If an option of 'element' exists, and is a SimpleXML object,
  // then it will be used directly. If it is a string, the string will
  // be assumed to be valid XML which will be constructed into a
  // SimpleXML object. If no 'element' option was passed, we use the
  // default value (an XML string) to construct a new SimpleXML object.
  protected function get_simple_xml_element ($opts)
  {
    if (isset($opts['element']))
    {
      if ($opts['element'] instanceof \SimpleXMLElement)
      {
        $xml = $opts['element'];
      }
      elseif (is_string($opts['element']))
      {
        $xml = new \SimpleXMLElement($opts['element']);
      }
      else
      {
        throw new Exception("Invalid XML passed.");
      }
    }
    elseif (isset($opts['parent_element']))
    {
      if ($opts['parent_element'] instanceof \SimpleXMLElement)
      {
        $parent = $opts['parent_element'];
      }
      elseif (is_string($opts['parent_element']))
      {
        $parent = new \SimpleXMLElement($opts['parent_element']);
      }
      else
      {
        throw new Exception("Invalid parent XML passed.");
      }

      if (isset($opts['child_element']))
      {
        $tag = $opts['child_element'];
      }
      elseif (isset($opts['default_tag']))
      {
        $tag = $opts['default_tag'];
      }
      else
      {
        $tag = $this->get_classname();
      }

      $xml = $parent->addChild($tag);
    }
    elseif (isset($opts['default_element']))
    {
      $defxml = $opts['default_element'];
      if ($defxml instanceof \SimpleXMLElement)
      {
        $xml = $defxml;
      }
      elseif (is_string($defxml))
      {
        $xml = new \SimpleXMLElement($defxml);
      }
      else
      {
        throw new Exception("Invalid default XML passed.");
      }
    }
    elseif (isset($opts['default_tag']))
    {
      $defxml = '<'.$opts['default_tag'].'/>';
      $xml = new \SimpleXMLElement($defxml);
    }
    else
    {
      $defxml = '<'.$this->get_classname().'/>';
      $xml = new \SimpleXMLElement($defxml);
    }
    return $xml;
  }

  // Load a SimpleXML object.
  public function load_simple_xml ($simplexml, $opts=Null)
  {
    throw new Exception("No load_simple_xml() method defined.");
  }

  // Output as a SimpleXML object.
  public function to_simple_xml ($opts=Null)
  {
    throw new Exception("No to_simple_xml() method defined.");
  }

  // Load an XML string.
  public function load_xml_string ($string, $opts=Null)
  {
    $simplexml = new \SimpleXMLElement($string);
    return $this->load_simple_xml($simplexml, $opts);
  }

  // Load a DOMNode object.
  public function load_dom_node ($dom, $opts=Null)
  {
    $simplexml = simplexml_import_dom($dom);
    return $this->load_simple_xml($simplexml);
  }

  // Return a DOMElement
  public function to_dom_element ($opts=Null)
  {
    $simplexml = $this->to_simple_xml($opts);
    $dom_element = dom_import_simplexml($simplexml);
    return $dom_element;
  }

  // Return a DOMDocument
  public function to_dom_document ($opts=Null)
  {
    $dom_element = $this->to_dom_element($opts);
    $dom_document = $dom_element->ownerDocument;
    return $dom_document;
  }

  // Return an XML string.
  public function to_xml ($opts=Null)
  {
    $simplexml = $this->to_simple_xml($opts);
    return $simplexml->asXML();
  }

  // Return the lowercase "basename" of our class.
  public function get_classname ($object=Null)
  {
    if (is_null($object))
      $object = $this;
    $classpath = explode('\\', get_class($object));
    $classname = strtolower(end($classpath));
    return $classname;
  }

  // Return the lowercase "dirname" of our class.
  public function get_namespace ($object=Null)
  {
    if (is_null($object))
      $object = $this;
    $classpath = explode('\\', get_class($object));
    array_pop($classpath); // Eliminate the "basename".
    $namespace = join('\\', $classpath);
    return $namespace;
  }

  /**
   * Get either our parent class, or a parent class in our heirarchy with
   * a given classname (as returned by get_classname(), so no Namespaces.)
   */
  public function get_parent ($class=Null)
  {
    $parent = $this->parent;
    if (is_null($class))
    {
      return $parent;
    }
    else
    { // The get_classname() function uses lowercase names.
      $class = strtolower($class);
    }

    if (isset($parent))
    {
      if (is_callable(array($parent, 'get_classname')))
      {
        $parentclass = $parent->get_classname();
      }
      else
      {
        $parentclass = $this->get_classname($parent);
      }

      if ($parentclass == $class)
      {
        return $parent;
      }
      elseif (is_callable(array($parent, 'get_parent')))
      {
        return $parent->get_parent($class);
      }
    }
  }

}

