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
 * @package Nano3\Utils\Translation
 *
 */

namespace Nano3\Utils;

/**
 * LangException: Thrown if something goes wrong.
 */
class LangException extends \Nano3\Exception {}

/**
 * Translation Class
 *
 * Creates the object which represents your translation database.
 */
class Translation
{
  /**
   * @var string  The default language to use.
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
   * @param string $lang    Optional. Language to use. Default: 'en'.
   */
  public function __construct ($data, $ns=array(), $lang='en')
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
        $nses = array($opts['setns']);
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

    if (isset($opts['lang']))
    {
      $lang = $opts['lang'];
    }
    else
    {
      $lang = $this->default_lang;
    }

    if (!isset($this->languages[$lang]))
    {
      throw new LangException("Invalid language: '$lang'");
    }

    $language = $this->languages[$lang];

    return array('namespaces' => $nses, 'language' => $language);
  }

  // Lookup a translation string.
  public function getStr ($key, $reps=Null, $opts=array())
  {
    $settings = $this->get_opts($opts);
    $nses = $settings['namespaces'];
    $language = $settings['language'];

    $matches = array();
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
          $return = array('text'=>$return);
        }
        $return['ns'] = $ns;
        if (isset($reps))
        {
          $return['text'] = vsprintf($return['text'], $reps);
        }
        return $return;
      }
      elseif (isset($reps))
      {
        return vsprintf($return, $reps);
      }
      return $return;
    }

    // If we reached here, we didn't find a value yet.
    if (isset($language['.inherits']))
    {
      $opts['lang'] = $language['.inherits'];
      return $this->getStr($key, $reps, $opts);
    }

    // If all else fails, return the original string.
    return $key;
  }

  // Generate a translation table from a flat array of keys.
  // With an optional prefix. It also supports default values.
  public function strArray ($array, $prefix='')
  {
    $assoc = array();
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
      $val = $this->getStr($prefix.$key);
      if ($val == $prefix.$key)
      {
        $val = $default;
      }
      $assoc[$key] = $val;
    }
    return $assoc;
  }

  // Reverse lookup of a string. If found, it returns the key.
  public function lookupStr ($string, $opts=array())
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
              $value = array(
                'ns'  => $ns,
                'key' => $key,
              );
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

}

