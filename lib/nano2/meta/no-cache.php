<?php

/* If you load this Meta library, we will set rules to
   say this page is not to be cached. This is useful for
   dynamic content such as data being sent via AJAX calls, etc.
 */

header('Cache-Control: no-cache, must-revalidate'); // the standard.
header('Expires: Thu, 22 Jun 2000 18:45:00 GMT');   // fixes IE bugs.

// End of meta library.
