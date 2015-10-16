<?php

namespace Nano4\Utils;

class Text
{
  static function make_identifier ($string)
  {
    return
      preg_replace('/[^A-Za-z_0-9]*/', '', preg_replace('/\s+/', '_', $string));
  }
}
