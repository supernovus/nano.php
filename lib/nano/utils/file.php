<?php

namespace Nano\Utils;
use finfo;

/**
 * A class representing a file.
 *
 * Has a whole bunch of wrappers for dealing with file uploads,
 * CSV files, and Zip files. 
 */
class File
{
  /**
   * The name of the file.
   */
  public $name;

  /**
   * Mime type, if any.
   */
  public $type;

  /**
   * The size of the file.
   */
  public $size;

  /**
   * The filename on the system.
   */
  public $file;

  /**
   * Set this to a valid text encoding ('UTF-8', 'UTF-16', etc.) if you
   * know the encoding of the file before hand. Otherwise auto-detection
   * of the encoding will be used.
   */
  public $encoding;
  
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
  public static function hasUpload ($name, $context=null)
  {
    if (isset($context))
    {
      if (isset($context->files[$name]) 
        && $context->files[$name]['error'] === UPLOAD_ERR_OK)
      {
        return True;
      }
      return False;
    }
    if (isset($_FILES[$name]) && $_FILES[$name]['error'] === UPLOAD_ERR_OK)
    {
      return True;
    }
    return False;
  }

  /**
   * Utility function used by getUpload/getUploads.
   */
  protected static function makeUploadFile ($file)
  {
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

  /**
   * Create a File object from a standard HTTP upload.
   *
   * @param string $name  The name of the upload field.
   * @param RouteContext $context  Optional: the current RouteContext object.
   *
   * @return mixed
   *
   * Returned value will be a File object if the upload was valid,
   * the PHP upload error code if the upload failed,
   * or null if the upload does not exist in the context, or was not
   * a single upload (use getUploads() for multiple files.)
   */
  public static function getUpload ($name, $context=null)
  {
    $files = isset($context) ? $context->files : $_FILES;
    if (isset($files, $files[$name], $files[$name]['error'])
      && is_scalar($files[$name]['error']))
    {
      return static::makeUploadFile($files[$name]);
    }
    return Null;
  }

  /**
   * Convert PHP's array style upload syntax into something more sane.
   *
   * PHP's multiple upload array syntax is weird:
   *
   * [
   *   "name" => 
   *   [
   *     "foo.txt",
   *     "bar.txt"
   *   ],
   *   "tmp_name" => 
   *   [
   *     "/tmp/file1", 
   *     "/tmp/file2"
   *   ],
   *   "error" => 
   *   [
   *     UPLOAD_ERR_OK, 
   *     UPLOAD_ERR_OK
   *   ],
   *   "type" => 
   *   [
   *     "text/plain", 
   *     "text/plain"
   *   ],
   *   "size" => 
   *   [
   *     123,
   *     456
   *   ],
   * ]
   *
   *  This converts that structure to this:
   *
   *  [
   *    [
   *      "name" => "foo.txt",
   *      "tmp_name" => "/tmp/file1",
   *      "error" => UPLOAD_ERR_OK,
   *      "type" => "text/plain",
   *      "size" => 123,
   *    ],
   *    [
   *      "name" => "bar.txt",
   *      "tmp_name" => "/tmp/file2",
   *      "error => UPLOAD_ERR_OK,
   *      "type" => "text/plain",
   *      "size" => 456,
   *    ],
   *  ]
   *
   * @param array $uploadArray  A PHP upload array to convert.
   * @return array  A sane upload array.
   */
  public static function reorderUploadArray ($uploadArrary)
  {
    $outputArray = [];
    foreach ($uploadArray as $key1 => $val1)
    {
      foreach ($val1 as $key2 => $val2)
      {
        $outputArray[$key2][$key1] = $val2;
      }
    }
    return $outputArray;
  }

  /**
   * Create an array of File objects from an array style multiple upload.
   *
   * Each element in the array will be a File object if the upload was
   * valid, or the PHP upload error code if the upload failed.
   *
   * If the upload was not found in the context, an empty array will be
   * returned.
   */
  public static function getUploads ($name, $context=null)
  {
    $uploads = [];
    $files = isset($context) ? $context->files : $_FILES;
    if (isset($files, $files[$name]))
    {
      if (is_array($files[$name]['error']))
      { // It's an array, let's do the thing.
        $uploadArray = static::reorderUploadArray($files[$name]);
        foreach ($uploadArray as $key => $file)
        {
          $uploads[$key] = static::makeUploadFile($file);
        }
      }
      elseif (is_scalar($files[$name]['error']))
      { // No array was used. Add the single file.
        $uploads[] = static::makeUploadFile($files[$name]);
      }
    }
    return $uploads;
  }

  /**
   * Create a File object from a Fine Uploader (qq.FileUploader) request.
   *
   * DEPRECATED, will be removed in v6. Use getUpload or getUploads instead.
   */
  public static function getUploadQQ ($name='qqfile')
  {
    error_log("getUploadQQ is DEPRECATED");
    return static::getUpload($name);
  }

  /**
   * Save the file to the specified folder (with it's original filename.)
   *
   * @param string $folder  The folder we are saving the file to.
   * @param bool $move  Move the file (default false).
   * @param bool $update  Update the file object (default true).
   *
   * @return {string|bool}  The new full path to the file if the save worked,
   *                        or false otherwise.
   */
  public function saveTo ($folder, $move=false, $update=true)
  { 
    $target = $folder . '/' . $this->name;
    return $this->saveAs($target, $move, $update);
  }

  /**
   * Save the file to the specified filename.
   *
   * The full path will be created if it does not exist.
   *
   * @param string $target  Where we want to save the file.
   * @param bool $move  Move the file (default true).
   * @param bool $update  Update the file object (default true).
   *
   * @return {string|bool}  The new full path to the file if the save worked,
   *                        or false otherwise.
   */
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
    return False;
  }

  /**
   * Make a copy of the file, without changing the original.
   *
   * @param string $target  The target path of the copy we are making.
   *
   * @return bool  Was the copy successful?
   */
  public function copyTo ($target)
  {
    $copy = $this->saveAs($target, false, false);
     return $copy == $target;
  }

  /**
   * Rename the file.
   *
   * @param string $newname  The new name of the file.
   *
   * @return bool  Was the file renamed successfully?
   */
  public function rename ($newname)
  {
    if (rename($this->file, $newname))
    {
      $this->file = $newname;
      return True;
    }
    return False;
  }

  /**
   * Update the file size.
   *
   * Use this if you have modified the file.
   */
  public function update_size ()
  {
    $this->size = filesize($this->file);
  }

  /**
   * Return the file contents as a string.
   *
   * @param bool $forceUTF8  Force the output to be UTF-8 (default false).
   *
   * @return string  The file contents (converted if $forceUTF8 was true.)
   *
   * @throws Exception  If forceUTF8 was true but we could not detect the
   *                    encoding, an Exception will be thrown.
   */
  public function getString ($forceUTF8=false)
  {
    $string = file_get_contents($this->file);
    if ($forceUTF8)
    {
      if (isset($this->encoding))
      { // Use the manually specified encoding.
        $encoding = $this->encoding;
      }
      else
      { // Try to detect the encoding.
        $bom = substr($string, 0, 2);
        if ($bom === chr(0xff).chr(0xfe) || $bom === chr(0xfe).chr(0xff))
        { // UTF-16 Byte Order Mark found.
          $encoding = 'UTF-16';
        }
        else
        {
          $encoding = mb_detect_encoding($string, 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP, ISO-8859-1', true);
        }
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

  /**
   * Update the file contents from a string.
   *
   * No conversion is done of the passed contents.
   *
   * @param mixed $data  The string (or blob) we are writing the the file.
   * @param array $opts  (Optional) Named options:
   *
   * 'append' (bool)  If true, use append mode instead of overwrite mode.
   * 'lock'   (bool)  If true, aquire a lock before writing.
   *
   * @return {int|bool}  The number of bytes written or false if error.
   */
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
   * @param array $opts  See CSV::parse() for a list of valid options.
   *
   * One added option is 'utf8' which defaults to true, and is passed to
   * getString() to determine if we should force UTF-8 strings. You probably
   * shouldn't change this unless you know what you are doing.
   *
   * @return array  The parsed CSV data.
   */
  public function getDelimited ($opts=[])
  {
    $forceutf8 = isset($opts['utf8']) ? $opts['utf8'] : true;
    $string = $this->getString($forceutf8);
    $rows = CSV::parse($string, $opts);
    return $rows;
  }

  /**
   * Read the contents of the file into an array.
   */
  public function getArray ()
  {
    return file($this->file);
  }

  /**
   * Put the contents of an array into the file.
   *
   * This is an alias of putString(), since it handles mixed data.
   */
  public function putArray ($data, $opts=[])
  {
    return $this->putString($data, $opts);
  }

  /**
   * Return a PHP stream resource for the file.
   *
   * @param string $mode  Mode to open the stream (default: 'rb').
   * @param bool $addBin  Add 'b' to the mode? (default: false).
   *
   * @return resource  The PHP stream resource.
   */
  public function openStream ($mode='rb', $addBin=false)
  {
    if ($addBin) $mode .= 'b';
    return fopen($this->file, $mode);
  }

  /**
   * Return a FileStream object representing this file.
   *
   * @param string $mode  Mode to open the stream (default: 'rb').
   * @param bool $addBin  Add 'b' to the mode? (default: false).
   *
   * @return FileStream
   */
  public function getStream ($mode='rb', $addBin=false)
  {
    return new FileStream($this, $mode, $addBin);
  }

  /**
   * Return a FileStream object in read mode.
   *
   * @param bool $bin  Use binary mode (default: true)
   *
   * @return FileStream
   */
  public function getReader ($bin=true)
  {
    return $this->getStream('r', $bin);
  }

  /**
   * Return a FileStream object in write mode.
   *
   * @param bool $bin  Use binary mode (default: true)
   *
   * @return FileStream
   */
  public function getWriter ($bin=true)
  {
    return $this->getStream('w', $bin);
  }

  /**
   * Return a FileStream object in append mode.
   *
   * @param bool $bin  Use binary mode (default: true)
   *
   * @return FileStream
   */
  public function getLogger ($bin=true)
  {
    return $this->getStream('a', $bin);
  }

  /**
   * Get the contents using a resource stream.
   *
   * You probably don't need this, getString() works for just about everything.
   */
  public function getContents ($bin=true)
  {
    $handle   = $this->openStream('r', $bin);
    $contents = fread($handle, $this->size);
    fclose($handle);
    return $contents;
  }

  /**
   * Put the contents using a resource stream.
   *
   * You probably don't need this, putString() works for just about everything.
   */
  public function putContents ($data, $bin=true)
  {
    $handle = $this->openStream('w', $bin);
    $count = fwrite($handle, $data);
    fclose($handle);
    if ($count !== False)
    {
      $this->update_size();
    }
    return $count;
  }

  /**
   * Open a ZipArchive object from this file.
   *
   * Returns a ZipArchive if the file could be opened, or
   * the error code if the file could not be opened.
   */
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

  /**
   * Extract a zip file to a temporary folder and return the path.
   *
   * @param string $prefix  Temp name prefix (default: 'zipfile_').
   *
   * @return mixed  If a 'string' it's the path to the temporary folder.
   *                If an int, it's the error code from getZip().
   *                If null, the zip could not be extracted.
   */
  public function getZipDir ($prefix='zipfile_')
  {
    $zipfile  = $this->getZip();
    if (!($zipfile instanceof \ZipArchive))
    {
      return $zipfile;
    }
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
   * @param  int     $prec     Precision for result (default: 2).
   * @param  array   $types    An array of strings to append for each type.
   *                 Default:  [' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB']
   *
   * @return string  The friendly size (e.g. "4.4 MB")
   */
  public static function filesize_str ($size, $prec=2, $types=null)
  {
    if (is_numeric($size))
    {
      $decr = 1024;
      $step = 0;
      if (!is_array($types))
      {
        $types = array(' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB');
      }
      while (($size / $decr) > 0.9)
      {
        $size = $size / $decr;
        $step++;
      }
      return round($size, $prec) . $type[$step];
    }
  }

  /**
   * Returns the friendly string representing our own file size.
   *
   * Uses filesize_str() to generate the string.
   *
   * @param int    $prec   Precision for result (default: 2)
   * @param array  $types  An array of strings to append for each type.
   *                       See filesize_str() for default value.
   *
   * @return string         The friendly size.
   */
  public function fileSize ($prec=2, $types=null)
  {
    return $this->filesize_str($this->size, $prec, $types);
  }

  /**
   * Returns the file stats.
   */
  public function stats ()
  {
    return stat($this->file);
  }

  /**
   * Return a DateTime object or formatted string from a stat field.
   *
   * @param  string  $field   The stat field ('atime','mtime','ctime').
   * @param  string  $format  Optional, a valid DateTime format string.
   *
   * @return mixed  If you passed $format this returns a string.
   *                If you didn't it returns a DateTime object.
   *                Returns null if the field does not exist.
   */
  public function getTime ($field, $format=null)
  {
    $stats = $this->stats();
    if (isset($stat[$field]))
    {
      $modtime  = $stat[$field];
      $datetime = new DateTime("@$modtime");
      if (isset($format) && is_string($format))
      {
        return $datetime->format($format);
      }
      else
      {
        return $datetime;
      }
    }
  }

  /**
   * Return the time when the file was last modified.
   *
   * @param   string  $format   Optional, a valid DateTime format string.
   *
   * See getTime() for valid return values.
   */
  public function modifiedTime ($format=null)
  {
    return $this->getTime('mtime', $format);
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

/**
 * A wrapper around PHP file streams that works with the File object.
 */
class FileStream
{
  /**
   * The File object associated with this FileStream object.
   */
  protected $file;

  /**
   * The PHP stream resource currently open.
   */
  protected $stream;

  /**
   * Have we opened a stream?
   */
  protected $open = false;

  /**
   * Create a new FileStream object with the given File object.
   *
   * @param File  $file  The File object for this stream.
   * @param string $mode  If non-null, we call open() with this mode.
   * @param bool $addBin  Passed to open() if $mode was set (default: false)
   */
  public function __construct ($file, $mode=null, $addBin=false)
  {
    $this->file = $file;
    if (isset($mode))
    {
      $this->open($mode, $addBin);
    }
  }

  /**
   * Return the File object.
   */
  public function getFile ()
  {
    return $this->file;
  }

  /**
   * Return the current stream.
   */
  public function getStream ()
  {
    return $this->stream;
  }

  /**
   * Open a stream.
   *
   * Will automatically close an already open stream if there is one.
   * Uses the File::openStream() method to actually open the stream.
   *
   * @param string  $mode  The mode to open the stream (e.g. 'r' or 'w')
   * @param bool  $addBin  Add 'b' to the mode? (default: false)
   */
  public function open ($mode, $addBin=false)
  {
    if ($this->open)
    { // Close the open stream.
      if (!$this->close())
      {
        return false;
      }
    }
    $this->stream = $file->openStream($mode, $addBin);
    $this->open = true;
    return true;
  }

  /**
   * Read data from the stream.
   *
   * @param int $bytes  How many bytes to read (default: whole file)
   */
  public function read ($bytes=null)
  {
    if (!$this->open) { return null; }
    if (!$bytes)
    {
      $bytes = $this->file->size;
    }
    return fread($this->stream, $bytes);
  }

  /**
   * Write data to the stream.
   *
   * @param mixed $data  Data to write to stream.
   */
  public function write ($data)
  {
    if (!$this->open) { return null; }
    return fwrite($this->stream, $data);
  }

  /**
   * Close the stream.
   *
   * Will automatically update the size of the File.
   */
  public function close ()
  {
    if ($this->open)
    {
      if (fclose($this->stream))
      {
        $this->file->update_size();
        $this->open = false;
        return true;
      }
    }
    return false;
  }

}

