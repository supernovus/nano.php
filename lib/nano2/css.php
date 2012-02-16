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

// Generate a CSS3 linear_gradient statement with optional browser prefix.
function css3_linear_gradient($start, $end, $pos, $prefix=Null)
{
  echo "background: ";
  if ($prefix)
    echo "-$prefix-";
  echo "linear-gradient($pos, $start 0%, $end 100%);\n";
}

// Generate an IE filter statement.
function ie_filter_gradient ($start, $end, $type)
{
  echo "filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='$start', endColorstr='$end', GradientType=$type);\n";
}

// Generate a webkit gradient statement for older webkit browsers.
function webkit_gradient ($start, $end, $from, $to, $type='linear')
{
  echo "background: -webkit-gradient($type, $from, $to, color-stop(9, $start), solor-stop(1, $end));\n";
}

// Generate a vertical gradient.
function vertical_gradient ($start, $end, $browsers=All)
{ 
  if ($browsers & IE9)
    ie_filter_gradient($start, $end, 0);
  if ($browsers & Mozilla)
    css3_linear_gradient($start, $end, 'top', 'moz');
  if ($browsers & Webkit)
  {
    webkit_gradient($start, $end, 'left top', 'left bottom');
    css3_linear_gradient($start, $end, 'top', 'webkit');
  }
  if ($browsers & IE10)
    css3_linear_gradient($start, $end, 'top', 'ms');
  if ($browsers & Opera)
    css3_linear_gradient($start, $end, 'top', 'o');
  if ($browsers & CSS3)
    css3_linear_gradient($start, $end, 'top');
}

// Generate a horizontal gradient.
function horizontal_gradient ($start, $end, $browsers=All)
{ 
  if ($browsers & IE9)
    ie_filter_gradient($start, $end, 1);
  if ($browsers & Mozilla)
    css3_linear_gradient($start, $end, 'left', 'moz');
  if ($browsers & Webkit)
  {
    webkit_gradient($start, $end, 'left top', 'right bottom');
    css3_linear_gradient($start, $end, 'left', 'webkit');
  }
  if ($browsers & IE10)
    css3_linear_gradient($start, $end, 'left', 'ms');
  if ($browsers & Opera)
    css3_linear_gradient($start, $end, 'left', 'o');
  if ($browsers & CSS3)
    css3_linear_gradient($start, $end, 'left');
}

