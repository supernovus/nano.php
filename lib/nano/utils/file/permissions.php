<?php

namespace Nano\Utils\File;

/**
 * Convert between Unix permissions strings and integer values.
 */
class Permissions
{
  /**
   * Convert a permission string into an integer value.
   *
   * @param str $string     The permission string we are parsing.
   * @param int $inmode     Initial mode value (default: 0).
   *
   * @return int   The integer mode value.
   *
   * NOTE: If the string is not 10 characters long exactly, or contains
   *       any of the letters u, g, a, or o, it will be converted using the
   *       convert_mod() function. This is the only time the $inmode is used.
   */
  static function parse ($string, $inmode=0)
  {
    if (strlen($string) != 10 || preg_match('/[ugao]+/', $string))
    { // Pass it off onto parse_mod()
      $string = static::convert_mod($string, $inmode);
    }

    $mode = 0;

    if ($string[1] == 'r') $mode += 0400;
    if ($string[2] == 'w') $mode += 0200;
    if ($string[3] == 'x') $mode += 0100;
    elseif ($string[3] == 's') $mode += 04100;
    elseif ($string[3] == 'S') $mode += 04000;

    if ($string[4] == 'r') $mode += 040;
    if ($string[5] == 'w') $mode += 020;
    if ($string[6] == 'x') $mode += 010;
    elseif ($string[6] == 's') $mode += 02010;
    elseif ($string[6] == 'S') $mode += 02000;

    if ($string[7] == 'r') $mode += 04;
    if ($string[8] == 'w') $mode += 02;
    if ($string[9] == 'x') $mode += 01;
    elseif ($string[9] == 't') $mode += 01001;
    elseif ($string[9] == 'T') $mode += 01000;

    return $mode;
  }

  /**
   * Encode an integer value into a 10 character string.
   *
   * @param int $int     The integer value we are encoding.
   * @param bool $isDir  Is this a directory? (Default: false).
   *
   * @return str  The permissions string.
   */
  static function encode ($int, $isDir=false)
  {
    $string = $isDir ? 'd' : '-';

    if ($int & 0400)
      $string .= 'r';
    else
      $string .= '-';
    if ($int & 0200)
      $string .= 'w';
    else
      $string .= '-';
    if ($int & 04000 && $int & 0100)
      $string .= 's';
    elseif ($int & 0100)
      $string .= 'x';
    elseif ($int & 04000)
      $string .= 'S';
    else
      $string .= '-';

    if ($int & 040)
      $string .= 'r';
    else
      $string .= '-';
    if ($int & 020)
      $string .= 'w';
    else
      $string .= '-';
    if ($int & 02000 && $int & 010)
      $string .= 's';
    elseif ($int & 010)
      $string .= 'x';
    elseif ($int & 02000)
      $string .= 'S';
    else
      $string .= '-';

    if ($int & 04)
      $string .= 'r';
    else
      $string .= '-';
    if ($int & 02)
      $string .= 'w';
    else
      $string .= '-';
    if ($int & 01000 && $int & 01)
      $string .= 't';
    elseif ($int & 01)
      $string .= 'x';
    elseif ($int & 01000)
      $string .= 'T';
    else
      $string .= '-';

    return $string;
  }

  /**
   * Convert a UGOA string into a 10 character permissions string.
   *
   * @param str $string     The UGOA string we are parsing.
   * @param int $inmode     The initial permissions value (default: 0).
   * @param bool $isDir     Is this a directory? (Default: false).
   *
   * @return int  The modified permissions value.
   *
   * Current Limitations/Differences from Unix chmod:
   *
   *  1.) If you use '=' it will override ALL permissions including s/S.
   *
   */
  static function convert_mod ($instring, $inmode=0, $isDir=false)
  {
    $outstring = static::encode($inmode, $isDir);
    $psets = explode(',', $instring);
    foreach ($psets as $pset)
    {
      $pdef = [];
      if (preg_match('/([ugoa]*)([\+\-\=])([rwxXstugo]+)/', $pset, $pdef))
      {
#        error_log("matched: ".json_encode($pdef));
        $ugoa  = $pdef[1];
        $op    = $pdef[2];
        $perms = $pdef[3];

        if ($perms == 'u')
        { // Copy permissions from user.
          $do_r = ($outstring[1] == 'r');
          $do_w = ($outstring[2] == 'w');
          $do_x = ($outstring[3] == 'x' || $outstring[3] == 's');
          $do_s = ($outstring[3] == 'S' || $outstring[3] == 's');
          $do_t = false;
        }
        elseif ($perms == 'g')
        { // Copy permissions from group.
          $do_r = ($outstring[4] == 'r');
          $do_w = ($outstring[5] == 'w');
          $do_x = ($outstring[6] == 'x' || $outstring[6] == 's');
          $do_s = ($outstring[6] == 'S' || $outstring[6] == 's');
          $do_t = false;
        }
        elseif ($perms == 'o')
        { // Copy permissions from others.
          $do_r = ($outstring[7] == 'r');
          $do_w = ($outstring[8] == 'w');
          $do_x = ($outstring[9] == 'x' || $outstring[9] == 't');
          $do_s = ($outstring[9] == 'T' || $outstring[9] == 't');
          $do_s = false;
        }
        else
        { // Check for rwxXst properties.
          $do_r = is_numeric(strpos($perms, 'r'));
          $do_w = is_numeric(strpos($perms, 'w'));
          $do_s = is_numeric(strpos($perms, 's'));
          $do_t = is_numeric(strpos($perms, 't'));
          if (is_numeric(strpos($perms, 'X')))
          { // If 'x' is true in any existing field, do 'x'.
            $do_x = 
            (
              $outstring[3] == 'x' || $outstring[3] == 's'
              || $outstring[6] == 'x' || $outstring[6] == 's'
              || $outstring[9] == 'x' || $outstring[9] == 't'
            );
          }
          else
          { // Check for 'x'.
            $do_x = is_numeric(strpos($perms, 'x'));
          }
        }

        if ($ugoa == '')
        {
          $ugoa = 'a';
        }

        if (is_numeric(strpos($ugoa, 'a')))
        { // All overrides everything else.
          $do_u = $do_g = $do_o = true;
        }
        else
        { // Look for 'u', 'g', and 'o' separately.
          $do_u = is_numeric(strpos($ugoa, 'u'));
          $do_g = is_numeric(strpos($ugoa, 'g'));
          $do_o = is_numeric(strpos($ugoa, 'o'));
        }

        $parse_op = function ($rc, $wc, $xc, $do_e, $eboth, $eonly)
          use (&$outstring, $op, $do_r, $do_w, $do_x)
        {
#          error_log("parseop($rc, $wc, $xc, $do_e, $eboth, $eonly) use ($outstring, $op, $do_r, $do_w, $do_x)");
          if ($op == '=')
          {
            if ($do_r)
              $outstring[$rc] = 'r';
            else
              $outstring[$rc] = '-';
            if ($do_w)
              $outstring[$wc] = 'w';
            else
              $outstring[$wc] = '-';
            if ($do_x && $do_e)
              $outstring[$xc] = $eboth;
            elseif ($do_x)
              $outstring[$xc] = 'x';
            elseif ($do_e)
              $outstring[$xc] = $eonly;
            else
              $outstring[$xc] = '-';
          }
          elseif ($op == '+')
          {
            if ($do_r)
              $outstring[$rc] = 'r';
            if ($do_w)
              $outstring[$wc] = 'w';
            if ($do_x && $do_e)
              $outstring[$xc] = $eboth;
            elseif ($do_x)
              $outstring[$xc] = ($outstring[$xc] == $eboth || $outstring[$xc] == $eonly) ? $eboth : 'x';
            elseif ($do_e)
              $outstring[$xc] = ($outstring[$xc] == $eboth || $outstring[$xc] == 'x') ? $eboth : $eonly;
          }
          elseif ($op == '-')
          {
            if ($do_r)
              $outstring[$rc] = '-';
            if ($do_w)
              $outstring[$wc] = '-';
            if ($do_x && $do_e)
              $outstring[$xc] = '-';
            elseif ($do_x)
              $outstring[$xc] = $outstring[$xc] == $eboth ? $eonly : '-';
            elseif ($do_e)
              $outstring[$xc] = $outstring[$xc] == $eboth ? 'x' : '-';
          }
        };

        if ($do_u)
        {
          $parse_op(1, 2, 3, $do_s, 's', 'S');
        }
        if ($do_g)
        {
          $parse_op(4, 5, 6, $do_s, 's', 'S');
        }
        if ($do_o)
        {
          $parse_op(7, 8, 9, $do_t, 't', 'T');
        }
      }
    }

    return $outstring;
  }

}
