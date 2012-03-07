<?php

/* 
 * Adds a 'messages' loader to Nano.
 */

$nano = \Nano3\get_instance();
$nano->addViews
(
 'messages',
 'views/messages',
 array('is_default'=>true)
);

// End of meta library.
