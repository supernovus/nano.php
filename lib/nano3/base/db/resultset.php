<?php

/* A special base class meant for use with DBModel/DBItem.
   It represents the results of a query as an iterable item.
 */

namespace Nano3\Base\DB;

class ResultSet implements \Iterator
{
  protected $query;       // The SQL query we represent.
  protected $bind;        // The bound data for the execute() statement.
  protected $parent;      // The DBModel which called us.

  protected $results;     // The PDOResult object representing our results.
  protected $current;     // The current item.

  protected $primary_key = 'id'; // The primary key used by our table.

  public function __construct ($query, $bind, $parent, $primary_key=Null)
  {
    $this->query       = $query;
    $this->bind        = $bind;
    $this->parent      = $parent;
    if (isset($primary_key))
      $this->primary_key = $primary_key;
  }

  public function rewind ()
  {
    $query = $this->parent->query($this->query);
    $query->execute($this->bind);
    $this->results = $query;
    $this->next();
  }

  public function current ()
  {
    return $this->parent->wrapRow($this->current);
  }

  public function next ()
  {
    $this->current = $this->results->fetch();
  }

  public function key ()
  {
    $pk = $this->primary_key;
    return $this->current[$pk];
  }

  public function valid ()
  {
    if ($this->current)
      return true;
    return false;
  }

}

