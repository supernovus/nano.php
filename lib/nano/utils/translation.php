<?php
/**
 * Translation Framework
 *
 * Making handling translations easy.
 * Based on my older Translation framework, but updated for simplicity.
 * NOTE: This is not directly compatibile with the older Translation framework
 * as namespaces are placed directly within languages alongside keys.
 * Also, "inherits" becomes ".inherits" in this implementation.
 *
 * @package Nano\Utils\Translation
 *
 */

namespace Nano\Utils;

/**
 * LangException: Thrown if something goes wrong.
 */
class LangException extends \Nano\Exception {}

/**
 * Translation Class
 *
 * Creates the object which represents your translation database.
 */
class Translation implements \ArrayAccess
{
  /**
   * @var string  The default language to use.
   *
   * If set to true, we use Accept-Language header.
   * If none can be found, we fallback to 'en'.
   */
  public $default_lang;

  /**
   * @var array   The namespaces to look in.
   */
  public $default_ns;

  private $languages; // Storage for our configuration.

  /**
   * Create our object.
   *
   * @param array  $data    An associative array representing the translations.
   * @param array  $ns      Optional. Default namespaces to look in.
   * @param string $lang    Optional. Language to use. Default: true
   */
  public function __construct ($data, $ns=[], $lang=true)
  {
    $this->languages    = $data;
    $this->default_ns   = $ns;
    $this->default_lang = $lang;
  }

  protected function get_opts ($opts)
  {
    // Build a namespaces and language options.
    if (isset($opts['setns']))
    {
      if (is_array($opts['setns']))
      {
        $nses = $opts['setns'];
      }
      else
      {
        $nses = [$opts['setns']];
      }
    }
    else
    {
      $nses = $this->default_ns;
      if (isset($opts['addns']))
      {
        if (is_array($opts['addns']))
        {
          foreach ($opts['addns'] as $addns)
          {
            $nses[] = $addns;
          }
        }
        else
        {
          $nses[] = $opts['addns'];
        }
      }
      if (isset($opts['insns']))
      {
        if (is_array($opts['insns']))
        {
          foreach ($opts['insns'] as $insns)
          {
            array_unshift($nses, $insns);
          }
        }
        else
        {
          array_unshift($nses, $opts['insns']);
        }
      }
    }

    $lang = 'en'; // Fallback to english.
    if (isset($opts['lang']))
    {
      $lang = $opts['lang'];
    }
    elseif (is_string($this->default_lang))
    {
      $lang = $this->default_lang;
    }
    elseif (is_bool($this->default_lang) && $this->default_lang)
    {
      $langs = Language::accept();
      foreach ($langs as $langkey => $weight)
      {
        if (isset($this->languages[$langkey]))
        {
          $lang = $langkey;
          break;
        }
      }
    }

    if (!isset($lang) || !isset($this->languages[$lang]))
    {
      throw new LangException("Invalid language: '$lang'");
    }

    $language = $this->languages[$lang];

    return ['namespaces' => $nses, 'language' => $language];
  }

  // Lookup a translation string.
  public function getStr ($key, $opts=[])
  {
    $settings = $this->get_opts($opts);
    $nses = $settings['namespaces'];
    $language = $settings['language'];

    $matches = [];
    if (preg_match('/^(\w+):/', $key, $matches))
    {
      $prefix = $matches[1];
      $strip  = $matches[0];
      array_unshift($nses, $prefix);
      $key = str_replace($strip, '', $key);
    }

    $value = Null;
    foreach ($nses as $ns)
    {
      if (
        isset($language[$ns]) 
        && is_array($language[$ns]) 
        && isset($language[$ns][$key])
      )
      {
        $value = $language[$ns][$key];
        break;
      }
    }
    if (isset($value))
    {
      if (is_array($value))
      {
        if (isset($opts['complex']) && $opts['complex'])
        {
          $return = $value;
        }
        elseif (isset($value['text']))
        {
          $return = $value['text'];
        }
        else
        {
          throw new LangException
            ("Invalid language definition: '$lang:$ns:$key'");
        }
      }
      else
      {
        $return = $value;
      }
      // Now let's see if there's anything else to do.
      if (isset($opts['complex']) && $opts['complex'])
      {
        if (!is_array($return))
        {
          $return = ['text'=>$return];
        }
        $return['ns'] = $ns;
        if (isset($opts['reps']))
        {
          $return['raw_text'] = $return['text'];
          $return['reps'] = $opts['reps'];
          $return['text'] = vsprintf($return['text'], $opts['reps']);
        }
        elseif (isset($opts['vars']))
        {
          $return['raw_text'] = $return['text'];
          $return['vars'] = $opts['vars'];
          foreach ($opts['vars'] as $varkey => $varval)
          {
            $return['text'] = str_replace($varkey, $varval, $return['text']);
          }
        }
        return $return;
      }
      elseif (isset($opts['reps']))
      {
        return vsprintf($return, $opts['reps']);
      }
      elseif (isset($opts['vars']))
      {
        foreach ($opts['vars'] as $varkey => $varval)
        {
          $return = str_replace($varkey, $varval, $return);
        }
      }
      return $return;
    }

    // If we reached here, we didn't find a value yet.
    if (isset($language['.inherits']))
    {
      $opts['lang'] = $language['.inherits'];
      return $this->getStr($key, $opts);
    }

    // If all else fails, return the original string.
    return $key;
  }

  // Generate a translation table from a flat array of keys.
  // With an optional prefix. It also supports default values.
  public function strArray ($array, $prefix='', $opts=[])
  {
    $assoc = [];
    foreach ($array as $index => $value)
    {
      if (is_numeric($index))
      {
        $key     = $value;
        $default = $value;
      }
      else
      {
        $key     = $index;
        $default = $value;
      }
      $val = $this->getStr($prefix.$key, $opts);
      if ($val == $prefix.$key)
      {
        $val = $default;
      }
      $assoc[$key] = $val;
    }
    return $assoc;
  }

  /** 
   * Generate a translation table from an associative array.
   * In this version, we support a complex nested structure,
   * were the first level key is the prefix (minus separator)
   * and the definitions nest from there.
   * The default separator between prefix and name is a period.
   * You can also specify an optional top-level prefix or namespace
   * which the rest of the prefixes must exist within. No separator
   * will be put between the namespace and the inner prefix, so specify
   * it in its entirety with closing colon (e.g. "mysection:")
   */
  public function strStruct ($array, $sep='.', $ns='', $opts=[])
  {
    $result = array();
    foreach ($array as $prefix => $def)
    {
      $result[$prefix] = 
        $this->strStruct_getDef($def, $prefix, $sep, $ns, $opts);
    }
    return $result;
  }

  // Private helper method for strStruct().
  private function strStruct_getDef ($def, $prefix, $sep, $ns, $opts)
  {
    $result = array();
    foreach ($def as $index => $value)
    {
      if (is_array($value))
      {
        $result[$index] = 
          $this->strStruct_getDef($value, $prefix, $sep, $ns, $opts);
        continue;
      }
      elseif (is_numeric($index))
      {
        $key     = $value;
        $default = $value;
      }
      else
      {
        $key     = $index;
        $default = $value;
      }
      $id  = $ns.$prefix.$sep.$key;
      $val = $this->getStr($id, $opts);
      if ($val == $id)
      {
        $val = $default;
      }
      $result[$key] = $val;
    }
    return $result;
  }

  // Reverse lookup of a string. If found, it returns the key.
  public function lookupStr ($string, $opts=[])
  {
    $settings = $this->get_opts($opts);
    $nses = $settings['namespaces'];
    $language = $settings['language'];

    foreach ($nses as $ns)
    {
      if (isset($language[$ns]) && is_array($language[$ns]))
      {
        foreach ($language[$ns] as $key=>$val)
        {
          if (is_string($val) && $val == $string)
          {
            if (isset($opts['complex']) && $opts['complex'])
            {
              $value = [
                'ns'  => $ns,
                'key' => $key,
              ];
              return $value;
            }
            else 
            {
              return $key;
            }
          }
          elseif 
            (is_array($val) && isset($val['text']) && $val['text'] == $string)
          {
            if (isset($opts['complex']) && $opts['complex'])
            {
              $value = $val;
              $value['ns']  = $ns;
              $value['key'] = $key;
              return $value;
            }
            else
            {
              return $key;
            }
          }
        }
      }
    }
    // If we reached here, we didn't find it.
    if (isset($language['.inherits']))
    {
      $opts['lang'] = $language['inherits'];
      return $this->lookupStr($string, $opts);
    }
    // If all else fails, the string was not found, return False.
    return False;
  }

  // ArrayAccess Interface.
  
  public function offsetExists ($offset) 
  { // Simple, but non-optimized way of determining if the key exists.
    $newoffset = $this->getStr($offset);
    return $newoffset == $offset ? false : true;
  }
  public function offsetUnset ($offset)
  {
    throw new LangException("Cannot unset translations.");
  }
  public function offsetSet ($offset, $value)
  {
    throw new LangException("Cannot set translations.");
  }
  public function offsetGet ($offset)
  { // Return the results of getStr with default options.
    return $this->getStr($offset);
  }

}

