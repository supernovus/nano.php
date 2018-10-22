<?php

namespace Nano\Utils\Text;

const ALIGN_LEFT   = STR_PAD_RIGHT;
const ALIGN_RIGHT  = STR_PAD_LEFT;
const ALIGN_CENTER = STR_PAD_BOTH;

const POS_FIRST  = -1;
const POS_MIDDLE = 0;
const POS_LAST   = 1;

const LINE_BEFORE = 1;
const LINE_AFTER  = 2;

function huc ($code)
{
  return html_entity_decode("&#x$code;", ENT_NOQUOTES, 'UTF-8');
}

function pad ($str, $len, $align=ALIGN_LEFT, $trunc='~', $pad=' ')
{
  if (strlen($str) > $len)
  {
    $tlen = $len - strlen($trunc);
    return substr($str, 0, $tlen).$trunc;
  }
  else
  {
    return str_pad($str, $len, $pad, $align);
  }
}

class Table
{
  public $trunc   = '~';
  public $pad     = ' ';
  public $deflen  = 16;
  public $term    = "\n";
  public $addline = 0;

  public $headerColor = "\033[1;38m";
  public $normalColor = "\e[0m";

  protected $tl = '2554';
  protected $tr = '2557';
  protected $tc = '2564';

  protected $bl = '255A';
  protected $br = '255D';
  protected $bc = '2567';

  protected $ll = '255F';
  protected $rl = '2562';
  protected $ml = '253C';

  protected $hh = '2550';
  protected $hl = '2500';
  protected $vl = '2502';
  protected $vh = '2551';

  protected $columns = [];

  public function __construct ($opts=[])
  {
    if (isset($opts['trunc']))
    {
      $this->trunc = $opts['trunc'];
    }
    if (isset($opts['pad']))
    {
      $this->pad = $opts['pad'];
    }
    if (isset($opts['deflen']))
    {
      $this->deflen = $opts['deflen'];
    }
    if (isset($opts['term']))
    {
      $this->term = $opts['term'];
    }
    if (isset($opts['addline']))
    {
      $this->addline = $opts['addline'];
    }
    if (isset($opts['headerColor']))
    {
      $this->headerColor = $opts['headerColor'];
    }
    if (isset($opts['normalColor']))
    {
      $this->normalColor = $opts['normalColor'];
    }
    if (isset($opts['columns']) && is_array($opts['columns']))
    {
      foreach ($opts['columns'] as $coldef)
      {
        if (is_array($coldef))
        {
          $this->addColumn($coldef);
        }
        elseif ($coldef instanceof Column)
        {
          $this->columns[] = $coldef;
        }
      }
    }
  }

  public function addColumn (Array $def)
  {
    $this->columns[] = new Column($this, $def);
  }

  public function addLine ($pos=POS_MIDDLE)
  {
    if ($pos === POS_FIRST)
    {
      $l = huc($this->hh);
      $s = huc($this->tl);
      $m = huc($this->tc);
      $f = huc($this->tr);
    }
    elseif ($pos == POS_LAST)
    {
      $l = huc($this->hh);
      $s = huc($this->bl);
      $m = huc($this->bc);
      $f = huc($this->br);
    }
    else
    {
      $l = huc($this->hl);
      $s = huc($this->ll);
      $m = huc($this->ml);
      $f = huc($this->rl);
    }
    
    $lc = count($this->columns)-1;
    $line = '';
    foreach ($this->columns as $c => $col)
    {
      if ($c == 0)
      { // First column.
        $line .= $s;
      }
      else
      { // Non-first columns.
        $line .= $m;
      }

      $line .= str_repeat($l, $col->length+2);

      if ($c == $lc)
      { // Last column.
        $line .= $f;
      }
    }

    return $line.$this->term;
  }

  public function addTop ()
  {
    return $this->addLine(POS_FIRST);
  }

  public function addBottom ()
  {
    return $this->addLine(POS_LAST);
  }

  public function addHeader (Array $def, $addTop=false, $addBottom=false)
  {
    $header = '';
    if ($addTop)
      $header .= $this->addTop();
    $opts =
    [
      'addline' => $addBottom ? LINE_AFTER : 0,
      'color'   => $this->headerColor,
    ];
    $header .= $this->addRow($def, $opts);
    return $header;
  }

  public function addRow (Array $def, $opts=[])
  {
    $addLine = isset($opts['addline']) ? $opts['addline'] : $this->addline;
    $color   = isset($opts['color'])   ? $opts['color']   : null;

    $cc = count($this->columns);
    $lc = $cc-1;
    if (count($def) != $cc)
    {
      throw new \Exception("Row doesn't have the right number of columns.");
    }

    if ($addLine & LINE_BEFORE)
      $row = $this->addLine();
    else
      $row = '';

    $l = huc($this->vl);
    $h = huc($this->vh);

    foreach ($this->columns as $c => $col)
    {
      if ($c == 0)
      { // First column.
        $row .= $h;
      }
      else
      { // Non-first columns.
        $row .= $l;
      }

      if (isset($color))
        $row .= $color;
      $row .= ' ' . $col->get($def[$c]) . ' ';
      if (isset($color))
        $row .= $this->normalColor;

      if ($c == $lc)
      { // Last column.
        $row .= $h;
      }
    }

    $row .= $this->term;

    if ($addLine & LINE_AFTER)
      $row .= $this->addLine();

    return $row;
  }
}

class Column
{
  protected $table;
  public $length = 16;
  public $align  = ALIGN_LEFT;

  public function __construct (Table $table, $opts=[])
  {
    $this->table = $table;
    if (isset($opts['length']))
    {
      $this->length = $opts['length'];
    }
    if (isset($opts['align']))
    {
      $this->align = $opts['align'];
    }
  }

  public function get ($text)
  {
    return pad($text, $this->length, $this->align, $this->table->trunc, $this->table->pad);
  }
}
