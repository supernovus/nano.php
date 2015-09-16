<?php

namespace Nano4\Utils;
use finfo;

/**
 * File class. Offers a bunch of wrappers to common file operations
 * including managing uploaded files.
 */

class File
{
  public $name;      // The name of the file.
  public $type;      // Mime type, if any.
  public $size;      // The size of the file.
  public $file;      // The filename on the system.
  
  /**
   * Build a new File object.
   */
  public function __construct ($file=Null)
  {
    if (isset($file))
    {
      $this->file = $file;
      $this->name = basename($file);
      if (file_exists($file))
      {
        $this->size = filesize($file);
        $finfo = new finfo(FILEINFO_MIME);
        $this->type = $finfo->file($file);
      }
    }
  }

  /**
   * See if a standard HTTP upload of a set name exists.
   */
  public static function hasUpload ($name)
  {
    if (isset($_FILES[$name]) && $_FILES[$name]['error'] === UPLOAD_ERR_OK)
    {
      return True;
    }
    return False;
  }

  /**
   * Create a File object from a standard HTTP upload.
   */
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

  /**
   * Create a File object from a Fine Uploader (qq.FileUploader) request.
   */
  public static function getUploadQQ ($name='qqfile')
  {
    #    error_log("In getUploadQQ");
    $class = __CLASS__;
    if (isset($_GET[$name]))
    {
#      error_log("  A GET parameter '$name' exists.");
      $input = fopen("php://input", "r");
      $tmpname = tempnam("/tmp", "qqupload_");
      $tmpfile = fopen($tmpname, "w");
      $size  = stream_copy_to_stream($input, $tmpfile);
      fclose($input);
      fclose($tmpfile);
      if ($size == (int)$_SERVER['CONTENT_LENGTH'])
      {
#        error_log("The size is correct.");
        $upload = new $class();
        $upload->name = $_GET[$name];
        $upload->size = $size;
        $upload->file = $tmpname;
        $finfo = new finfo(FILEINFO_MIME);
        $upload->type = $finfo->file($tmpname);
        return $upload;
      }
      return ['error'=>'Upload size mismatch'];
    }
    elseif (isset($_FILES[$name]))
    {
#      error_log("  A FILES element '$name' exists.");
      return $class::getUpload($name);
    }
#    error_log("Could not find anything called '$name', sorry.");
    return Null;
  }

  public function saveTo ($folder, $move=false, $update=true)
  { 
    $target = $folder . '/' . $this->name;
    return $this->saveAs($target, $move, $update);
  }

  public function saveAs ($target, $move=true, $update=true)
  {
#    error_log("saveAs($target,".json_encode($move).",".json_encode($update).")");
#    error_log("file: ".$this->file);
    if (!file_exists($this->file))
    {
      return false;
    }
    $target_dir = dirname($target);
    if (!is_dir($target_dir))
    {
#      error_log("directory '$target_dir' does not exist?");
      mkdir($target_dir, 0755, true);
      chmod($target_dir, 0755);
    }
    if (copy($this->file, $target))
    {
#      error_log("copied");
      if ($move)
      {
        unlink($this->file);    // Delete the old one.
      }
      if ($update)
      {
        $this->file = $target;           // Change our file pointer.
        $this->name = basename($target); // Change our file name.
      }
      return $target;         // And return the new name.
    }
#    else
#    {
#      error_log("couldn't copy: " . `ls -l {$this->file}`);
#    }
    return False;
  }

  public function copyTo ($target)
  {
    $copy = $this->saveAs($target, false, false);
     return $copy == $target;
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

  public function update_size ()
  {
    $this->size = filesize($this->file);
  }

  public function getString ($forceUTF8=false)
  {
    $string = file_get_contents($this->file);
    if ($forceUTF8)
    {
      $bom = substr($string, 0, 2);
      if ($bom === chr(0xff).chr(0xfe) || $bom === chr(0xfe).chr(0xff))
      { // UTF-16 Byte Order Mark found.
        $encoding = 'UTF-16';
      }
      else
      {
        $encoding = mb_detect_encoding($string, 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP, ISO-8859-1', true);
      }
      if ($encoding)
      {
        if ($encoding != 'UTF-8')
        {
          $string = mb_convert_encoding($string, 'UTF-8', $encoding);
        }
      }
      else
      {
        throw new \Exception("Unsupported document encoding found.");
      }
    }
    return $string;
  }

  public function putString ($data, $opts=[])
  {
    $flags = 0;
    if (isset($opts['append']) && $opts['append'])
    {
      $flags = FILE_APPEND;
    }
    if (isset($opts['lock']) && $opts['lock'])
    {
      $flags = $flags | LOCK_EX;
    }
    $count = file_put_contents($this->file, $data, $flags);
    if ($count !== False)
    {
      $this->update_size();
    }
    return $count;
  }

  /**
   * Parse a Delimiter Seperated Values file (defaults to Tab.)
   *
   * See CSV::parse() for a list of valid options.
   */
  public function getDelimited ($opts=[])
  {
    $forceutf8 = isset($opts['utf8']) ? $opts['utf8'] : true;
    $string = $this->getString($forceutf8);
    $rows = CSV::parse($string, $opts);
    return $rows;
  }

  public function getArray ()
  {
    return file($this->file);
  }

  public function putArray ($data, $opts=[])
  {
    return $this->putString($data, $opts);
  }

  public function getHandle ($mode='rb', $addBin=null)
  {
    if (isset($addBin) && $addBin) $mode .= 'b';
    return fopen($this->file, $mode);
  }

  public function getReader ($bin=true)
  {
    return $this->getHandle('r', $bin);
  }

  public function getWriter ($bin=true)
  {
    return $this->getHandle('w', $bin);
  }

  public function getLogger ($bin=true)
  {
    return $this->getHandle('a', $bin);
  }

  public function getContents ($bin=true)
  {
    $handle   = $this->getHandle('r', $bin);
    $contents = fread($handle, $this->size);
    fclose($handle);
    return $contents;
  }

  public function putContents ($data, $bin=true)
  {
    $handle = $this->getHandle('w', $bin);
    $count = fwrite($handle, $data);
    fclose($handle);
    if ($count !== False)
    {
      $this->update_size();
    }
    return $count;
  }

  public function getZip ()
  {
    $zip = new \ZipArchive;
    $res = $zip->open($this->file);
    if ($res === TRUE)
    {
      return $zip;
    }
    else
    {
      return $res;
    }
  }

  public function getZipDir ($prefix='zipfile_')
  {
    $zipfile  = $this->getZip();
    $tempfile = tempnam("/tmp", $prefix);
    if (file_exists($tempfile)) unlink($tempfile);
    mkdir($tempfile);
    if (is_dir($tempfile))
    {
      $zipfile->extractTo($tempfile);
      return $tempfile;
    }
  }

  /**
   * Convert a size in bytes to a friendly string.
   *
   * @param  number  $size     The size in bytes you want to convert.
   * @return string            The friendly size (e.g. "4.4 MB")
   */
  public function filesize_str ($size)
  {
    if (is_numeric($size))
    {
      $decr = 1024;
      $step = 0;
      $type = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
      while (($size / $decr) > 0.9)
      {
        $size = $size / $decr;
        $step++;
      }
      return round($size, 2) . ' ' . $type[$step];
    }
  }

  /**
   * Returns the friendly string representing our own file size.
   *
   * Uses filesize_str() to generate the string.
   *
   * @return string         The friendly size.
   */
  public function fileSize ()
  {
    return $this->filesize_str($this->size);
  }

  /**
   * Returns the file stats.
   */
  public function stats ()
  {
    return stat($this->file);
  }

  /**
   * Return the time when the file was last modified.
   * 
   * @param   string  $format   Optional, a valid DateTime Format.
   * @return  mixed             A DateTime object, or a string, depending
   *                            on if you passed the $format option.
   */
  public function modifiedTime ($format=Null)
  {
    $stats    = $this->stats();
    $modtime  = $stat['mtime'];
    $datetime = new DateTime("@$modtime");
    if (isset($format))
    {
      return $datetime->format($format);
    }
    else
    {
      return $datetime;
    }
  }

  /**
   * Recursively remove an entire directory tree.
   */
  public static function rmtree ($path)
  { 
    if (is_dir($path))
    {
      foreach (scandir($path) as $name)
      {
        if (in_array($name, array('.', '..'))) continue;
        $subpath = $path.DIRECTORY_SEPARATOR.$name;
        self::rmtree($subpath);
      }
      rmdir($path);
    }
    else
    {
      unlink($path);
    }
  }

}
