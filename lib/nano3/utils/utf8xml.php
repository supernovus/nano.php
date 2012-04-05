<?php

namespace Nano3\Utils;

/**
 * Convert everything from UTF-8 into an NCR[numeric character reference]
 *
 * Borrowed from http://php.net/manual/en/function.htmlentities.php
 * Thanks to user "montana" for this unique solution.
 *
 * This has been enhanced with several extra features as were required
 * during development of systems using Unicode with XML.
 *
 * I've also reformatted and renamed all of the original methods.
 *
 */

class UTF8XML 
{
  /**
   * Encode a Unicode string to an XML document using NCR.
   *
   * @param string  $content    The unicode string to encode.
   * @param bool    $quotes     Optional. Encode quote characters.
   * @return string             The encoded string.
   */
  public function encode ($content, $quotes=false) 
  {
    $content = $this->encode_xml_chars($content, $quotes);
    $contents = $this->to_array($content);
    $swap = "";
    $iCount = count($contents);
    for ($o=0;$o<$iCount;$o++) 
    {
      $contents[$o] = $this->encode_utf8_char($contents[$o]);
      $swap .= $contents[$o];
    }
    return mb_convert_encoding($swap,"UTF-8"); //not really necessary, but why not.
  }

  /**
   * An alternative to html_entity_encode used internally.
   *
   * @param string  $string     The string to encode.
   * @param bool    $quotes     Optional. Encode quote characters.
   * @return string             The encoded string.
   */
  public function encode_xml_chars ($string, $quotes=false)
  { 
    $string = str_replace('<', '&#x3C;', $string);
    $string = str_replace('>', '&#x3E;', $string);
    $string = $this->fix_ampersand($string);
    if ($quotes)
    { 
      $string = str_replace("'", '&#x27;', $string);
      $string = str_replace('"', '&#x22;', $string);
    }
    return $string;
  }

  /**
   * Encode any ampersand characters which are not entities.
   *
   * @param string  $string     String to encode.
   * @return string             The encoded string.
   */
  public function fix_ampersand ($string)
  { 
    return preg_replace('/\&(?!#?\w+;)/', '&#x26;', $string);
  }

  /**
   * Convert a unicode string into an array.
   *
   * @param string $string        String to convert.
   * @return array                The array representing the string.
   */
  public function to_array( $string ) 
  { //adjwilli
    $strlen = mb_strlen($string);
    $array = array();
    while ($strlen) 
    {
      $array[] = mb_substr( $string, 0, 1, "UTF-8" );
      $string = mb_substr( $string, 1, $strlen, "UTF-8" );
      $strlen = mb_strlen( $string );
    }
    return $array;
  }

  /**
   * Replace a UTF8 character with a NCR.
   *
   * @param string  $c         The character to replace.
   * @return string            The NCR string representing the character.
   */    
  public function encode_utf8_char ($c) 
  { //m. perez 
    $h = ord($c{0});    
    if ($h <= 0x7F) 
    { 
      return $c;
    } 
    else if ($h < 0xC2) 
    { 
      return $c;
    }
            
    if ($h <= 0xDF) 
    {
      $h = ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
      $h = "&#" . $h . ";";
      return $h; 
    } 
    else if ($h <= 0xEF) 
    {
      $h = ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6 | (ord($c{2}) & 0x3F);
      $h = "&#" . $h . ";";
      return $h;
    } 
    else if ($h <= 0xF4) 
    {
      $h = ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12 
        | (ord($c{2}) & 0x3F) << 6 | (ord($c{3}) & 0x3F);
      $h = "&#" . $h . ";";
      return $h;
    }
  }

  /**
   * Decode a previously encoded string.
   *
   * @param string  $content     An XML string.
   * @return string              A UTF-8 string.
   */
  public function decode ($content)
  {
    return html_entity_decode($content, ENT_QUOTES, 'UTF-8');
  }

} 

// End of library.
