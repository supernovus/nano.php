<?php

namespace Nano\Utils\Text;

/**
 * A class to output terminal color codes.
 *
 * Currently only supports the basic 16 color mode.
 */
class Colors
{
  const E = "\e[";

  const S_DK = 0; // Dark style
  const S_LT = 1; // Light style
  const S_UL = 4; // Underline style
  const S_RV = 7; // Reversed style

  const T_FG = 3; // Foreground color prefix
  const T_BG = 4; // Background color prefix

  // Color postfixes.
  const C_BLACK  = 0; 
  const C_RED    = 1; 
  const C_GREEN  = 2;
  const C_YELLOW = 3;
  const C_BLUE   = 4;
  const C_PURPLE = 5;
  const C_CYAN   = 6;
  const C_WHITE  = 7;

  const NORMAL = self::E.'0m';

  // And some predefined colors for backwards compatibility
  // with the original version of this library.
  const RED = self::E.'0;31m';
  const BG_WHITE = self::E.'0;47m';
  const BLUE = self::E.'0;34m';
  const CYAN = self::E.'0;36m';
  const BG_RED = self::E.'41m';
  const BLACK = self::E.'0;30m';
  const GREEN = self::E.'0;32m';
  const GRAY = self::E.'0;90m';
  const BROWN = self::E.'0;33m';
  const BG_BLUE = self::E.'0;44m';
  const BG_CYAN = self::E.'0;46m';
  const PURPLE = self::E.'0;35m';
  const MAGENTA = self::E.'0;35m';
  const BG_BLACK = self::E.'0;40m';
  const BG_GREEN = self::E.'0;42m';
  const YELLOW = self::E.'0;33m';
  const BG_YELLOW = self::E.'0;43m';
  const BG_MAGENTA = self::E.'0;45m';
  const DARK_GRAY = self::E.'1;30m';
  const LIGHT_RED = self::E.'1;31m';
  const LIGHT_BLUE = self::E.'1;34m';
  const LIGHT_CYAN = self::E.'1;36m';
  const LIGHT_GRAY = self::E.'0;37m';
  const LIGHT_GREEN = self::E.'1;32m';
  const LIGHT_WHITE = self::E.'1;38m';
  const BG_LIGHT_GRAY = self::E.'0;47m';
  const LIGHT_PURPLE = self::E.'1;35m';
  const LIGHT_YELLOW = self::E.'1;93m';
  const LIGHT_MAGENTA = self::E.'1;35m';

  /**
   * A quick way to get a color code.
   *
   * @param mixed $opts  Either an array of options, or null.
   *
   *  'fg'        (string)  The foreground color.
   *  'bg'        (string)  The background color.
   *  'bold'      (bool)    Make it bold/light;
   *  'thin'      (bool)    The opposite of bold.
   *  'underline' (bool)    Underline the text.
   *  'reverse'   (bool)    Reverse the colors.
   *
   * If it's null, we reset back to normal colors.
   *
   * The accepted color strings are:
   *
   * 'black'
   * 'red'
   * 'green'
   * 'yellow'
   * 'blue'
   * 'purple'
   * 'cyan'
   * 'white'
   *
   * Actually, it's a bit more flexible as only the first three letters
   * of the word are parsed, and it's case insensitive.
   *
   * @return string  The full escape code sequence ready to be printed.
   */
  public static function get ($opts=null)
  {
    if (is_array($opts))
    {
      $color = self::E;
      $cset = false;
      if (isset($opts['bold']) && $opts['bold'])
      {
        $color .= self::S_LT;
        $cset = true;
      }
      elseif (isset($opts['dark']) && $opts['dark'])
      {
        $color .= self::S_DK;
        $cset = true;
      }
      elseif (isset($opts['underline']) && $opts['underline'])
      {
        $color .= self::S_UL;
        $cset = true;
      }
      elseif (isset($opts['reverse']) && $opts['reverse'])
      {
        $color .= self::S_RV;
        $cset = true;
      }

      if (isset($opts['fg']))
      {
        if ($cset)
          $color .= ';';
        $color .= self::get_code($opts['fg']);
        $cset = true;
      }

      if (isset($opts['bg']))
      {
        if ($cset)
          $color .= ';';
        $color .= self::get_code($opts['bg'], true);
      }

      $color .= 'm';
      return $color;
    }
    else
    {
      return self::NORMAL;
    }
  }

  /**
   * A shortcut to setting the foreground color.
   *
   * @param string $color  The color for the foreground.
   * @param bool $bold  (Optional, default false) Should the text be bold?
   * @param array $opts  (Optional) Additional options for get().
   */
  public static function fg ($color, $bold=false, Array $opts=[])
  {
    $opts['fg'] = $color;
    $opts['bold'] = $bold;
    return self::get($opts);
  }

  /**
   * A shortcut to setting the background color.
   *
   * @param string $color  The color for the background.
   * @param bool $bold  (Optional, default false) Should the text be bold?
   * @param array $opts (Optional) Additional options for get().
   */
  public static function bg ($color, $bold=false, Array $opts=[])
  {
    $opts['bg'] = $color;
    $opts['bold'] = $bold;
    return self::get($opts);
  }

  /**
   * Parse a color name and return the code for it.
   *
   * @param string $colorname  The name of the color.
   * @param bool $bg  (Optional, default false) Is it a background color?
   * @return string  The two digit color code.
   */
  public static function get_code ($colorname, $bg=false)
  {
    if ($bg)
    {
      $code = self::T_BG;
      $def = self::C_BLACK;
    }
    else
    {
      $code = self::T_FG;
      $def = self::C_WHITE;
    }

    $colorname = strtolower(substr($colorname, 0, 3));
    switch ($colorname)
    {
      case 'black':
        $code .= self::C_BLACK;
        break;
      case 'red':
        $code .= self::C_RED;
        break;
      case 'gre':
        $code .= self::C_GREEN;
        break;
      case 'yel':
        $code .= self::C_YELLOW;
        break;
      case 'blu':
        $code .= self::C_BLUE;
        break;
      case 'pur':
        $code .= self::C_PURPLE;
        break;
      case 'cya':
        $code .= self::C_CYAN;
        break;
      case 'whi':
        $code .= self::C_WHITE;
        break;
      default:
        $code .= $def;
    }

    return $code;
  }
}
