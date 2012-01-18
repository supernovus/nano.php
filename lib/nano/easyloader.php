<?php

/* EasyLoader: Build loaders dynamically, including global variables, etc. */

function build_loader ($name, $dir)
{
  $build = "global \$__nano_${name}_dir;
    \$__nano_${name}_dir = '$dir';
    make_loader('$name');";
  eval($build);
}

## End of library.