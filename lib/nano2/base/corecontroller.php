<?php

// This class represents a controller foundation.
// The controller can have multiple models, and can load
// templates consisting of a layout, and a screen.
// The contents of the screen will be made available as the
// $view_content variable in the layout.
// You should create a base class to extend this that provides
// any application-specific common controller methods.
// The Nano instance must have the 'controllers' extension in its
// loaded library list for this to work.

$nano = get_nano_instance();
$nano->loadMeta('templates');
$nano->loadMeta('models');

abstract class CoreController 
{ public $models = array(); // Any Models we have loaded.
  public $layout;           // The default layout to use.
  protected $default_url;   // Where redirect() goes if no URL is specified.

  // Process a screen template with the given data.
  public function process_template ($screen, $data, $layout=NULL)
  { $nano = get_nano_instance();
    if (is_null($layout))
      $layout = $this->layout;
    // Okay, let's get the view screen output.
    $page = $nano->lib['screens']->load($screen, $data);
    if (isset($layout))
    { // We're using a layout model.
      // Please ensure your layout has a view_content variable.
      $data['view_content'] = $page;
      $template = $nano->lib['layouts']->load($layout, $data);
      return $template;
    }
    else
    { // We're going to directly return the content of the view.
      return $page;
    }
  }

  // Load a data model.
  protected function load_model ($model, $opts=array())
  { $nano = get_nano_instance();
    $this->models[$model] = $nano->lib['models']->load($model, $opts);
  }

  // Return our controller name.
  public function name ()
  {
    $nano = get_nano_instance();
    return $nano->lib['controllers']->id($this);
  }

  // Redirect to another page. This ends the current PHP process.
  public function redirect ($url=null, $relative=true)
  {
    if (is_null($url))
      $url = $this->default_url;
    if ($relative)
    {
      $url = $this->url() . $url;
    }
    header("Location: $url");
    exit;
  }

  // Return our URL.
  public function url ()
  {
    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on")
    { $defport = "443";
      $proto = "https";
    }
    else
    { $defport = "80";
      $proto = "http";
    }
    $port = ($_SERVER["SERVER_PORT"] == $defport) ? '' : (":".$_SERVER["SERVER_PORT"]);
    return $proto."://".$_SERVER['SERVER_NAME'].$port;
  }


}

