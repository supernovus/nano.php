<?php

namespace Nano\Utils;

/**
 * A wrapper around the phpSpreadsheet library that offers a few additional
 * features, such as being able to return a zipped collection of CSV files.
 * At this time, the library is write-only. It does not read existing
 * spreadsheet files. That may change in the future.
 */

if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet'))
{
  throw new \Exception("Missing phpSpreadsheet library.");
}

use PhpOffice\PhpSpreadsheet as phps;

class Spreadsheet
{
  /**
   * The default output format. At this time we only support:
   *
   *  'Xlsx'  Excel 2007+ format.
   *  'Xls'   Legacy Excel format.
   *  'Ods'   OpenDocument format.
   *  'Csv'   CSV files (specially processing handled here.)
   *
   */
  public $defaultFormat = 'Xlsx';
  public $tempDir = '/tmp';
  public $tempPre = 'nano_spreadsheet_';
  public $zipCsv  = true;

  protected $spreadsheet;
  protected $worksheets = [];

  public function __construct ($opts=[])
  {
    $this->spreadsheet = new phps\Spreadsheet();
  }

  public function getSpreadsheet ()
  {
    return $this->spreadsheet;
  }

  public function addWorksheet ($opts=[])
  {
    if (count($this->worksheets) == 0)
    { // No worksheets registered, use the default one.
      $opts['worksheet'] = $this->spreadsheet->getActiveSheet();
    }
    $ws = new Worksheet($this, $opts);
    $this->worksheets[] = $ws;
    return $ws;
  }

  public function compile ($opts=[])
  {
    $tempdir  = isset($opts['tempdir'])  ? $opts['tempdir']  : $this->tempDir;
    $prefix   = isset($opts['prefix'])   ? $opts['prefix']   : $this->tempPre;
    $filename = isset($opts['filename']) ? $opts['filename'] : 
      tempnam($tempdir, $prefix);

    $format = isset($opts['format']) 
      ? ucfirst(strtolower($opts['format'])) // Custom format selected.
      : $this->defaultFormat;                // Use default format.

#    error_log("Using spreadsheet format: $format");

    $writer = phps\IOFactory::createWriter($this->spreadsheet, $format);

    if (isset($opts['preCalculate']))
    {
      $writer->setPreCalculateFormulas($opts['preCalculate']);
    }

    $meth = "compile_$format";
    if (is_callable([$this, $meth]))
    { // A custom method compiles this format.
      return $this->$meth($writer, $filename, $opts);
    }
    else
    { // Use the writer's save() method directly.
      $writer->save($filename);
      return $filename;
    }
  }

  /**
   * CSV files can only have a single worksheet, so to work around that
   * limitation, you can choose to zip the contents.
   */
  protected function compile_Csv ($writer, $filename, $opts=[])
  {
    $csvopts = isset($opts['csv']) ? $opts['csv'] : $opts;

    $zip = isset($csvopts['zip']) ? $csvopts['zip'] : $this->zipCsv;
    $all = isset($csvopts['all']) ? $csvopts['all'] : $zip;

    // If 'all' is false, this is the sheet we will get.
    $single = isset($csvopts['sheet']) ? $csvopts['sheet'] : 0;

    // The following options are only used if $zip is true.
    $close = isset($csvopts['close']) ? $csvopts['close'] : true;
    $retzip = isset($csvopts['returnZip']) ? $csvopts['returnZip'] : !$close;
    $delcsv = isset($csvopts['delete'])? $csvopts['delete'] : true;

    if (isset($csvopts['delimiter']))
    {
      $writer->setDelimiter($csvopts['delimiter']);
    }
    if (isset($csvopts['enclosure']))
    {
      $writer->setEnclosure($csvopts['enclosure']);
    }
    if (isset($csvopts['terminator']))
    {
      $writer->setLineEnding($csvopts['terminator']);
    }
    if (isset($csvopts['bom']))
    {
      $writer->setUseBOM($csvopts['bom']);
    }

    if ($zip)
    { // We're going to build a zip file with each worksheet added to it.
      $zipfile = Zip::create($filename);
    }

    if ($all)
    { // Compiling all worksheets into CSV files.
      $compiled = [];
      foreach ($this->worksheets as $w => $ws)
      {
        $writer->setSheetIndex($w);
        $subname = $ws->getIdentifier() . '.csv';
        $subpath = $filename.'_'.$subname;
        $writer->save($subpath);
      }
    }
    else
    { // Save the selected worksheet.
      $ws = $this->worksheets[$single];
      if ($zip)
      { // Save the worksheet file to a subfile that will be added to the zip.
        $subname = $ws->getIdentifier() . '.csv';
        $subpath = $filename.'_'.$subname;
        $writer->save($subpath);
      }
      else
      { // Save the worksheet file to the filename directly.
        $writer->save($filename);
      }
    }

    if ($zip)
    { // Add compiled files to the zip file.
      if ($all)
      { // Adding a collection of files.
        foreach ($compiled as $subname => $subpath)
        {
          $zipfile->addFile($subpath, $subname);
          if ($delcsv)
          {
            unlink($subpath);
          }
        }
      }
      else
      { // Adding a single file.
        $zipfile->addFile($subpath, $subname);
        if ($delcsv)
        {
          unlink($subpath);
        }
      }

      if ($close)
      { // Close the zip file.
        $zipfile->close();
      }
    }

    if ($zip && $retzip)
    { // Zip file with the object requested.
      return ['zip'=>$zipfile, 'filename'=>$filename];
    }
    elseif ($all && !$zip)
    { // All files selected, but not in zip format.
      return $compiled;
    }
    else
    { // One file to return.
      return $filename;
    }
  }

} // class Spreadsheet

class Worksheet
{
  protected $parent;
  protected $worksheet;

  public function __construct (Spreadsheet $parent, $opts=[])
  {
    $this->parent = $parent;
    $ss = $parent->getSpreadsheet();
    if (isset($opts['worksheet']))
    {
      $this->worksheet = $opts['worksheet'];
    }
    else
    {
      $index = isset($opts['index']) ? $opts['index'] : null;
      $this->worksheet = $ss->createSheet($index);
    }
    if (isset($opts['name']))
    {
      $this->rename($opts['name']);
    }
    if (isset($opts['data']))
    {
      $this->populateData($opts['data']);
    }
  }

  public function getWorksheet ()
  {
    return $this->worksheet;
  }

  public function getName ()
  {
    return $this->worksheet->getTitle();
  }

  public function getIdentifier ()
  {
    return Text::make_identifier($this->getName());
  }

  public function getParent ()
  {
    return $this->parent;
  }

  public function rename ($newname)
  {
    $this->worksheet->setTitle($newname);
  }

  public function populateData ($data)
  {
    if (!is_array($data))
    {
      error_log("Unhandled data type passed to Worksheet::populateData()");
      return;
    }
    if (isset($data['rows']) && is_array($data['rows']))
    { // The rows were passed as a named structure.
      $rows = $data['rows'];
      if (isset($data['cols']) && is_array($data['cols']))
      { // The columns were passed as a named structure.
        array_unshift($rows, $data['cols']);
      }
    }
    elseif (isset($data[0]) && is_array($data[0]))
    { // The whole table structure was passed as the data.
      $rows = $data;
    }
    else
    { // No valid row definitions found.
      error_log("Invalid row definitions passed to Worksheet::populateData()");
      return;
    }
    foreach ($rows as $r => $row)
    {
      if (!is_array($row))
      {
        error_log("Invalid row found, skipping.");
        continue;
      }
      foreach ($row as $c => $col)
      {
        $this->worksheet->setCellValueByColumnAndRow($c, $r, $col);
      }
    }
  }

} // class Worksheet
