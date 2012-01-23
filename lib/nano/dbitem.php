<?php

/* A base class representing individual items from the model.
   For use in an ORM-style model.
   You can call $item->save(); to update the database.
   The constructor requires the hash results from a query,
   the DBModel object which created it, and the table to save results to.
 */

class DBItemException extends Exception {}

class DBItem implements ArrayAccess
{
  protected $data;             // The hash data returned from a query.
  protected $parent;           // The DBModel object that created us.
  protected $table;            // The database table to update with save().
  protected $primary_key;      // The key for our identifier (default 'id'.)

  // Can't get much easier than this.
  public function __construct ($data, $parent, $table, $primary_key='id')
  {
    $this->data        = $data;
    $this->parent      = $parent;
    $this->table       = $table;
    $this->primary_key = $primary_key;
  }

  // Ensure a requested field exists in our schema.
  protected function __is_field ($name)
  {
    if (array_key_exists($name, $this->data))
      return true;
    else
      throw new DBItemException('Unknown field');
  }

  // Set a database field.
  public function __set ($name, $value)
  {
    if ($name == $this->primary_key)
      throw new DBItemException('Cannot overwrite primary key.');

    if ($this->__is_field($name))
      $this->data[$name] = $value;
  }

  // Get a database field.
  public function __get ($name)
  {
    if ($this->__is_field($name))
      return $this->data[$name];
  }

  // See if a database field is set.
  // For our purposes, '' is considered unset.
  public function __isset ($name)
  {
    if ($this->__is_field($name))
      return (isset($this->data[$name]) && $this->data[$name] != '');
  }

  // Sets a field to null.
  public function __unset ($name)
  {
    if ($name == $this->primary_key)
      throw new DBItemException('Cannot unset primary key');

    if ($this->__is_field($name))
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

}

