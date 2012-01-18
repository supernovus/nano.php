<?php

// Templates consisting of Layouts and Screens, for Nano.
load_core('views');

// Load a layout view
function load_layout ($view, $data=NULL)
{
  return load_view("layouts/$view", $data);
}

// Load a screen view
function load_screen ($view, $data=NULL)
{
  return load_view("screens/$view", $data);
}

// end of library.
