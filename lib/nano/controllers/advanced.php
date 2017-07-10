<?php

namespace Nano\Controllers;

/**
 * Advanced controller.
 *
 * Provides far more functionality than the Basic controller.
 * Including:
 *
 *  - Array access for view data.
 *  - Authenticated users using the Users model or subclass.
 *  - Model configuration, including Database integration.
 *  - Translatable text and status messages.
 *  - A view loader for e-mail messages (now called 'mail')
 *
 * You should define "page.default", "page.login" and "layout.default"
 * options in your Nano object.
 */

abstract class Advanced extends Basic implements \ArrayAccess
{
  use ViewData, Themes, Defaults, ModelConf, Messages, Auth, Mailer, Resources;
}

