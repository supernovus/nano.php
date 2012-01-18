<?php
/* The load_base() and load_lib() methods previously part of nano.php */

global $__nano_base_dir;
global $__nano_lib_dir;
$__nano_base_dir = 'lib/base';
$__nano_lib_dir  = 'lib/common';
make_loader('base');
make_loader('lib');
