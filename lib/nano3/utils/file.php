<?php

/**
 * File class. Offers a bunch of wrappers to common file operations
 * including managing uploaded files.
 */

namespace Nano3\Utils;

class File
{
  public $name;      // The name of the file.
  public $type;      // Mime type, if any.
  public $size;      // The size of the file.
  public $file;      // The filename on the system.

  public function __construct ($file=Null)
  {
    if (isset($file) && file_exists($file))
    {
      $this->file = $file;
      $this->name = basename($file);
      $this->size = filesize($file);
      $finfo = new finfo(FILEINFO_MIME);
      $this->type = $finfo->file($file);
    }
  }

  public static function hasUpload ($name)
  {
    if (isset($_FILES[$name]) && $_FILES[$name]['error'] === UPLOAD_ERR_OK)
    {
      return True;
    }
    return False;
  }

  public static function getUpload ($name)
  {
    if (isset($_FILES[$name]))
    {
      $file = $_FILES[$name];
      if ($file['error'] === UPLOAD_ERR_OK)
      {
        $class = __CLASS__;
        $upload = new $class();
        $upload->name = $file['name'];
        $upload->type = $file['type'];
        $upload->size = $file['size'];
        $upload->file = $file['tmp_name'];
        return $upload;
      }
      return $file['error'];
    }
    return Null;
  }

  public function copyTo ($folder)
  { 
    $target = $folder . '/' . $this->name;
    return $this->saveAs($target);
  }

  public function copyAs ($target)
  {
    if (copy($this->file, $target))
    {
      unlink($this->file);    // Delete the old one.
      $this->file = $target;  // Change our file pointer.
      return $target;         // And return the new name.
    }
    return False;
  }

  public function rename ($newname)
  {
    if (rename($this->file, $newname))
    {
      $this->file = $newname;
      return True;
    }
    return False;
  }

  public function getString ()
  {
    file_get_contents($this->file);
  }

  public function getArray ()
  {
    file($this->file);
  }

  public function getContents ()
  {
    $handle   = fopen($this->file, 'rb');
    $contents = fread($handle, $this->size);
    fclose($handle);
    return $contents;
  }

}
