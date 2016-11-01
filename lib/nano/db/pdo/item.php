<?php

namespace Nano\DB\PDO;

/**
 * A base class for SQL database models.
 */
class Item extends \Nano\DB\Child
{
  use \Nano\Data\JSON;

  /**
   * If set to an array of field names, only those fields will be
   * used when finding the id of a newly created row.
   */
  public $new_query_fields;

  /**
   * Save our data back to the database.
   *
   * If the primary key is set, and has not been modified, this will
   * update the existing record with the new data.
   *
   * If the primary key has not been set, or has been modified, this will
   * insert a new record into the database, and in the case of auto-generated
   * primary keys, update our primary key field to point to the new record.
   */
  public function save ($opts=[])
  {
    if (isset($opts['pk']))
      $pk = $opts['pk'];
    else
      $pk = $this->primary_key;

    if (is_callable([$this, 'to_row']))
      $data = $this->to_row($opts);
    else
      $data = $this->data;

    if (isset($this->data[$pk]) && !isset($this->modified_data[$pk]))
    { // Update an existing row.
      if (count($this->modified_data)==0) return;
      $fields = array_keys($this->modified_data);
      $cdata  = [];
      $fc = count($fields);
      for ($i=0; $i< $fc; $i++)
      {
        $field = $fields[$i];
        if ($field == $pk) continue; // Sanity check.
        $cdata[$field] = $data[$field];
      }
      $where = [$pk => $data[$pk]];
      $this->parent->update($where, $cdata);
      return True;
    }
    else
    { // Insert a new row.
      $model = $this->parent;
      $opts = ['return'=>$model::return_key];
      if ($this->auto_generated_pk)
        $setpk = False;
      else
        $setpk = True;

      if ($setpk)
        $opts['allowpk'] = True;

      if (isset($this->new_query_fields))
        $opts['cols'] = $this->new_query_fields;

      // Insert the row and get the new primary key.
      $newpk = $this->parent->insert($data, $opts);

      // Clear the modified data.
      $this->modified_data = [];

#      error_log("save got new '$pk' value '$newpk'");

      if ($setpk && isset($this->data[$pk])) return True; // We're done.
      elseif ($newpk)
      {
        $this->data[$pk] = $newpk;
        return True;
      }
      return False;
    }
  }

  /** 
   * Delete this item from the database.
   */
  public function delete ()
  {
    $pk = $this->primary_key;
    $where = [$pk => $this->data[$pk]];
    return $this->parent->delete($where);
  }

  /**
   * Convert to an array. Override this as required.
   */
  public function to_array ($opts=[])
  {
    return $this->data;
  }

} // end class Item

