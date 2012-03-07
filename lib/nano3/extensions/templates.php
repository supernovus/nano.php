<?php

/* Adds 'layouts' and 'screens' loaders to Nano.
   Layouts must be in a 'views/layouts' folder.
   Screens must be in a 'views/screens' folder.
   None of the folders should be inside 'lib'.
 */

$nano = \Nano3\get_instance();

// First add layout.
$nano->addViews
(
 'layouts',
 'views/layouts'
);

// Now add screen.
$nano->addViews
(
 'screens',
 'views/screens'
);

// End of meta library.