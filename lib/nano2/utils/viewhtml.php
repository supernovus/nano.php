<?php

// A view helper. This echos directly, so don't use outside of views.

function html_options ($array, $selected=Null, $is_mask=False)
{
  foreach ($array as $value=>$label)
  {
    echo "<option value=\"$value\"";
    if 
    (
      isset($selected)
      &&
      (
        ($is_mask && ($value & $selected))
        ||
        ($value == $selected)
      )
    )
    {
      echo ' selected="selected"';
    }
    echo ">$label</option>\n";
  }
}

