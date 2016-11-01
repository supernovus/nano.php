<?php

namespace Nano\DB\PDO\Simple;

/**
 * Enable some PHP commands to use ALTER TABLE commands.
 */
trait Alter
{
  /**
   * Modify an existing column.
   */
  public function change_column ($table, $oldname, $newname, $def)
  {
    $this->db->exec("ALTER TABLE $table CHANGE $oldname $newname $def");
  }

  /**
   * Add a new column.
   */
  public function add_column ($table, $name, $def)
  {
    $this->db->exec("ALTER TABLE $table ADD $name $def");
  }
}
