<?php

/* A base class representing individual items from the model.
   For use in an ORM-style model.
   You can call $item->save(); to update the database.
   The constructor requires the hash results from a query,
   the DBModel object which created it, and the table to save results to.
 */

namespace Nano3\Base\DB;

class Item implements \ArrayAccess
{
  protected $data;             // The hash data returned from a query.
  protected $parent;           // The DBModel object that created us.
  protected $table;            // The database table to update with save().

  protected $primary_key = 'id';  // The key for our identifier (default 'id'.)

  // To make aliases to the database field names, override the $aliases
  // member in your sub-classes. It should be an associative array, where
  // the key is the alias, and the value is the target database field.
  // NOTE: Aliases are not known about by the Model or ResultSet objects, 
  // so functions like getRowByField and manual SELECT statements must use 
  // the real database field names, not aliases! 
  protected $aliases = array();

  // Can't get much easier than this.
  public function __construct ($data, $parent, $table, $primary_key=Null)
  { 
    $this->data        = $data;
    $this->parent      = $parent;
    $this->table       = $table;
    if (isset($primary_key))
      $this->primary_key = $primary_key;
  }

  // This method looks for a field.
  // If the field exists in the database row,
  // it's returned unchanged. If it does not, but an
  // alias exists, the alias target field will be returned.
  // If neither exists, an exception will be thrown.
  protected function db_field ($name)
  {
    if (array_key_exists($name, $this->data))
      return $name;
    elseif (array_key_exists($name, $this->aliases))
      return $this->aliases[$name];
    else
      throw new Exception('Unknown field');
  }

  // Set a database field.
  public function __set ($name, $value)
  {
    $name = $this->db_field($name);

    if ($name == $this->primary_key)
      throw new Exception('Cannot overwrite primary key.');

    $this->data[$name] = $value;
  }

  // Get a database field.
  public function __get ($name)
  {
    $name = $this->db_field($name);
    return $this->data[$name];
  }

  // See if a database field is set.
  // For our purposes, '' is considered unset.
  public function __isset ($name)
  {
    $name = $this->db_field($name);
    return (isset($this->data[$name]) && $this->data[$name] != '');
  }

  // Sets a field to null.
  public function __unset ($name)
  {
    $name = $this->db_field($name);

    if ($name == $this->primary_key)
      throw new Exception('Cannot unset primary key');

    $this->data[$name] = null;
  }

  public function offsetExists ($name)
  {
    return $this->__isset($name);
  }

  public function offsetSet ($name, $value)
  {
    return $this->__set($name, $value);
  }

  public function offsetUnset ($name)
  {
    return $this->__unset($name);
  }

  public function offsetGet ($name)
  {
    return $this->__get($name);
  }

  // Save our data back to the database.
  public function save ()
  { // It may look like voodoo, but it's pretty simple really.
    $pk = $this->primary_key;
    $sql = "UPDATE {$this->table} SET ";
    $fields = array_keys($this->data);
    $fc = count($fields);
    $data = array();
    for ($i=0; $i < $fc; $i++)
    {
      $field = $fields[$i];
      $data[":$field"] = $this->data[$field];
      if ($field == $pk)
        continue; // We don't set the primary key.
      $sql .= "$field = :$field";
      if ($i != $fc - 1)
        $sql .= ', ';
    }
    $sql .= " WHERE $pk = :$pk";
    $query = $this->parent->query($sql);
    $query->execute($data);
  }

  // Delete this item from the database.
  public function delete ()
  {
    $pk = $this->primary_key;
    $sql = "DELETE FROM {$this->table} WHERE $pk = :$pk";
    $query = $this->parent->query($sql);
    $data = array (":$pk" => $this->data[$pk]);
    $query->execute($data);
  }

}

// End of base class.
