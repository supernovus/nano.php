<?php

/* Adds a 'view' loader to Nano.
   Views must be in a 'views' folder (not within 'lib')
 */

$nano = \Nano3\get_instance();
$nano->addViews
(
 'views',
 'views'
);

// End of meta library.
