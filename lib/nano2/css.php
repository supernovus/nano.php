<?php

/* A standalone helper for building dynamic CSS stylesheets.
   Makes things like gradients and other magic CSS tricks easier.
   This is not meant to be loaded as a part of Nano itself, but in a
   .php file that outputs a CSS stylesheet.
 */

header('Content-Type: text/css');
define('Mozilla', 1); // Mozilla based browsers.
define('Firefox', 1); // Alias for Mozilla.
define('Webkit',  2); // Webkit based browsers.
define('Chrome',  2); // Alias for Webkit.
define('Safari',  2); // Alias for Webkit.
define('IE10',    4); // Internet Explorer 10 and higher.
define('IE9',     8); // IE 6 through 9 use the same code.
define('IE8',     8); // ...
define('IE7',     8); // ...
define('IE6',     8); // ...
define('Opera',  16); // Opera
define('CSS3',   32); // CSS3 standard.
define('All',    63); // All of the above (and also the default.)

// Used by vertical_gradient() to generate the statements based on CSS3.
function css3_vertical_gradient($start, $end, $prefix=Null)
{
  echo "background: ";
  if ($prefix)
    echo "-$prefix-";
  echo "linear-gradient(top left, $start 0%, $end 100%);\n";
}

// Generate a vertical gradient. This does not do fallback, that's up to you.
function vertical_gradient ($start, $end, $browsers=All)
{ 
  if ($browsers & IE9)
    echo "filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='$start', endColorstr='$end');\n";
  if ($browsers & Mozilla)
    css3_vertical_gradient($start, $end, 'moz');
  if ($browsers & Webkit)
  {
    echo "background: -webkit-gradient(linear, left top, right bottom, color-stop(0, $start), color-stop(1, $end));\n";
    css3_vertical_gradient($start, $end, 'webkit');
  }
  if ($browsers & IE10)
    css3_vertical_gradient($start, $end, 'ms');
  if ($browsers & Opera)
    css3_vertical_gradient($start, $end, 'o');
  if ($browsers & CSS3)
    css3_vertical_gradient($start, $end);
}
