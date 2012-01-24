<?php

/* Adds a 'view' loader to Nano.
   Views must be in a 'views' folder (not within 'lib')
 */

$nano = get_nano_instance();
$nano->addViews
(
 'views',
 'views'
);

// End of meta library.
