<?php

/* Adds a 'components' loader to Nano, which loads Nano+ components.
   Nano+ components exist within the Nano library tree, and have specific
   API requirements. If you attempt to load a component without fulfilling
   the requirements, an error will be thrown.
 */

$nano = get_nano_instance();
$nano->addClass
(
 'components',
 NANODIR.'/components',
 'component'
);

// End of meta library.
