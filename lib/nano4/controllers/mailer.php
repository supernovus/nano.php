<?php

namespace Nano4\Controllers;

/**
 * Trait to add a Mail template view with optional language-specific
 * features if we're also using the Messages trait.
 *
 * Define a property called 'email_path' if you want to override the default
 * mailer view folder of 'views/mail'.
 */

trait Mailer
{
  protected function __construct_mailer_controller ($opts=[])
  {
    $nano = \Nano4\get_instance();
    if (property_exists($this, 'email_path'))
    {
      if (is_array($this->email_path))
      {
        $dirs = $this->email_path;
      }
      else
      {
        $dirs = [$this->email_path];
      }
    }
    else
    {
      $dirs = ['views/mail'];
    }

    $lang = $this->get_prop('lang', Null);

    $nano->mail_messages = 'views';
    foreach ($dirs as $dir)
    {
      if (isset($lang))
        $nano->mail_messages->addDir($dir . '/' . $lang);

      $nano->mail_messages->addDir($dir);
    }
  }

}
