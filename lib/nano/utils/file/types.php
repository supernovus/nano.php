<?php

namespace Nano\Utils\File;

/**
 * A quick mapping of common MIME types based on file extension.
 *
 * This is very basic, for enhanced functionality, use finfo.
 */

final class Types
{
  const mso  = 'application/vnd.openxmlformats-officedocument.';

  public static function types ()
  {
    return array
    (
      'xml'   => 'text/xml',
      'html'  => 'text/html',
      'xhtml' => 'application/xhtml+xml',
      'xlsx'  => self::mso . 'spreadsheetml.sheet',
      'xltx'  => self::mso . 'spreedsheetml.template',
      'potx'  => self::mso . 'presentationml.template',
      'ppsx'  => self::mso . 'presentationml.slideshow',
      'pptx'  => self::mso . 'presentationml.presentation',
      'sldx'  => self::mso . 'presentationml.slide',
      'docx'  => self::mso . 'wordprocessingml.document',
      'dotx'  => self::mso . 'wordprocessingml.template',
    );
  }

  public static function get ($type)
  {
    $types = self::types();
    if (isset($types[$type]))
    {
      return $types[$type];
    }
  }

}
