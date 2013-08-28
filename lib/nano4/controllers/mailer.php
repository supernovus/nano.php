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
  protected function __construct_mailer ($opts=[])
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

    if (property_exists($this, 'lang'))
    {
      $lang = '/' . $this->lang;
    }
    else
    {
      $lang = '';
    }

    $nano->mail_messages = 'views';
    foreach ($dirs as $dir)
    {
      $nano->mail_messages->addDir($dir . $lang);
    }
  }

}
