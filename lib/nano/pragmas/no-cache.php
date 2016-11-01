<?php

/**
 * Do not cache the HTTP output from this request.
 */

header('Cache-Control: no-cache, must-revalidate'); // the standard.
header('Expires: Thu, 22 Jun 2000 18:45:00 GMT');   // fixes IE bugs.

