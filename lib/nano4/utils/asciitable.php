<?php

namespace Nano4\Utils;

class ASCIITable
{
  protected $lengths = [];

  protected $rows = [];

  protected $currow = 0;
  protected $curcol = 0;

  // TODO: implement me

  public function updateCell ($row, $col, $text)
  {
    if (isset($this->rows[$row], $this->rows[$row][$col]))
    {
      $this->rows[$row][$col] = $text;
      $len = strlen($text);
      if ($len > $this->lengths[$col])
      {
        $this->lengths[$col] = $len;
      }
    }
  }
}
