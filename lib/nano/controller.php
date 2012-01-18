<?php

// This class represents a controller foundation.
// The controller can have multiple models, and can load
// templates consisting of a layout, and a screen.
// The contents of the screen will be made available as the
// $view_content variable in the layout.
// You should create a base class to extend this that provides
// any application-specific common controller methods.

abstract class CoreController 
{ public $models = array(); ## Any Models we have loaded.
  public $layout;           ## The default layout to use.
  public function process_template ($screen, $data, $layout=NULL)
  { load_core('templates');
    if (is_null($layout))
      $layout = $this->layout;
    // Okay, let's get the view screen output.
    $page = load_screen($screen, $data);
    if (isset($layout))
    { // We're using a layout model.
      // Please ensure your layout has a view_content variable.
      $data['view_content'] = $page;
      $template = load_layout($layout, $data);
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      return $page;
    }
  }
  public function load_model ($model, $opts=array())
  { load_core('models');
    $this->models[] = load_model($model, $opts);
  }
}

