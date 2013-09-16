<?php

/**
 * A base class for database-driven models.
 *
 * It wraps around the PDO library, and provides some very simplistic
 * ORM capabilities. You don't have to use them, but if you want them
 * they are there. The built-in ORM represents a single table. 
 * For more advanced methods such a multi-table support, you can use the
 * query() method directly and write methods in your extended class.
 */

namespace Nano4\DB;

abstract class Model implements \Iterator, \ArrayAccess
{
  protected $db;             // Our database object.

  protected $table;          // Our database table.
  protected $childclass;     // The class name for our children.
                             // Must extend the \Nano4\DB\Item class.
  protected $resultclass;    // Class name for iterable result set. 
                             // Must extend the \Nano4\DB\ResultSet class.

  protected $primary_key;    // The primary key on the database table.
                             // Defaults to 'id' if not specified.

  protected $resultset;      // Used if you use the iterator interface.

  protected $dsn;            // The DSN we were initialized with.
  protected $dbname;         // The database name/identifier.

  public $parent;            // The object which spawned us.

  public $known_fields;      // If set, it's a list of fields we know about.
                             // DO NOT set the primary key in here!

  protected $get_fields;     // Set to fields to get if none are specified.

  // The following are used by the pager() function to generate ORDER BY and LIMIT statements.
  public $sort_orders = array();
  public $sort_items  = array();
  public $default_sort_order;
  public $default_page_count = 10;

  // Constants used in the newRow() method.
  const return_row = 1; // Return a proper Row object.
  const return_raw = 2; // Return a raw DB query object.
  const return_key = 3; // Return the primary key value.

  /**
   * Return our table name.
   */
  public function get_table ()
  {
    $table = $this->table;
    return $table;
  }

  /**
   * Return the name of our class.
   *
   * This depends on the use of the Nano4 Models loader.
   * If you are not using the models loader, override this.
   */
  public function name ()
  {
    $nano = \Nano4\get_instance();
    return $nano->models->class_id($this);
  }

  /**
   * Build a new Model object.
   */
  public function __construct ($opts=array())
  {
    if (!isset($opts['dsn']))
      throw new Exception("Must have a database DSN");

    $this->dsn = $opts['dsn'];

    if (isset($opts['user']) && isset($opts['pass']))
      $this->db = new \PDO($opts['dsn'], $opts['user'], $opts['pass']);
    else
      $this->db = new \PDO($opts['dsn']);

    if (isset($opts['table']))
      $this->table = $opts['table'];
    elseif (!isset($this->table))
      $this->table = $this->name();
    if (isset($opts['childclass']))
      $this->childclass = $opts['childclass'];
    if (isset($opts['resultclass']))
      $this->resultclass = $opts['resultclass'];
    if (isset($opts['primary_key']))
      $this->primary_key = $opts['primary_key'];
    elseif (!isset($this->primary_key))
      $this->primary_key = 'id';

    $pk = $this->primary_key;

    // Default sort orders if you don't override it.
    if (count($this->sort_orders) == 0)
    {
      if (count($this->sort_items) == 0)
      {
        $this->sort_items = array($pk);
      }
      $this->build_sort_orders();
    }

    // If no default sort order has been specified, we do it now.
    if (!isset($this->default_sort_order))
    {
      $sort_orders = array_keys($this->sort_orders);
      $this->default_sort_order = $sort_orders[0];
    }

    if (isset($opts['parent']))
      $this->parent = $opts['parent'];
  }

  // Build a default set of sort_orders based on sort_items.
  protected function build_sort_orders ()
  {
    foreach ($this->sort_items as $sortref => $sortrow)
    {
      if (is_numeric($sortref)) 
      {
        $sortref = $sortrow;
      }

      $this->sort_orders[$sortref.'_up']   = "$sortrow ASC";
      $this->sort_orders[$sortref.'_down'] = "$sortrow DESC";
    }
  }

  /**
   * Look up the database name. We will cache the result.
   */
  public function database_name ()
  {
    if (isset($this->dbname))
      return $this->dbname;

    $matches = array();

#    error_log("Looking for dbname in ".$this->dsn);

    if (preg_match('/dbname=(\w+)/', $this->dsn, $matches))
    {
      $this->dbname = $matches[1];
    }
    elseif (preg_match('/sqlite:(\w+)/', $this->dsn, $matches))
    {
      $this->dbname = $matches[1];
    }
    else
    { // We could not determine the value, returning False.
      $this->dbname = False;
    }

    return $this->dbname;
  }

  /**
   *  Create a prepared statement, and set its default fetch style.
   */
  public function query ($statement, $assoc=True)
  {
    $query = $this->db->prepare($statement);
    if ($assoc)
      $query->setFetchMode(\PDO::FETCH_ASSOC);
    else
      $query->setFetchMode(\PDO::FETCH_NUM);
    return $query;
  }

  /** 
   * Wrap the results of fetch() in a nice object.
   *
   * If the hash is not set, or is false it returns null.
   * If our childclass is false, we return the unchanged row.
   */
  public function wrapRow ($rowhash)
  { 
    if ($rowhash)
    {
      $object = $this->newChild($rowhash);
      if (isset($object))
        return $object;
      else
        return $rowhash;
    }
  }

  /** 
   * Get an instance of our child class, representing a row.
   *
   * This is used by wrapRow() for existing rows, and can be used directly
   * to return a child row with no primary key set, that can be inserted into
   * the database using $row->save();
   *
   * If the known_fields class member is set, it will be used to ensure
   * all of the known fields are passed to the child class with some form
   * of default value (if not specified in the known_fields array, the
   * default value is 0.)
   */
  public function newChild ($data=array())
  {
    if (isset($this->known_fields) && is_array($this->known_fields))
    {
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        {
          $field   = $val;
          $default = 0;
        }
        else
        {
          $field   = $key;
          $default = $val;
        }
        if (!array_key_exists($field, $data))
        { // Add a placeholder value, to ensure the field is present.
          $data[$field] = $default;
        }
      }
    }
    $class = $this->childclass;
    if ($class)
    {
      $class = $this->childclass;
      $object = new $class($data, $this, $this->table, $this->primary_key);
      return $object;
    }
  }

  /**
   * Check and see if a field is known.
   */
  public function is_known ($field)
  {
    // First check to see if it is the primary key.
    if ($field == $this->primary_key)
    {
      return True;
    }

    if (isset($this->known_fields) && is_array($this->known-fields))
    {
      // Next look through our known_fields.
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        {
          $name = $val;
        }
        else
        {
          $name = $key;
        }
        if ($field == $name)
        {
          return True;
        }
      }
    }

    return False;
  }

  /** 
  * Return a ResultSet representing an executed query.
   *
   * If it cannot find the resultclass, it instead executes the statement
   * and returns the PDOResult object.
   */
  public function execute ($statement, $data=array(), $class=Null)
  {
    if (!isset($class))
      $class = $this->resultclass;

    if (!$class)
    {
      $query = $this->query($statement);
      $query->execute($data);
      return $query; // The raw query object.
    }

    $object = new $class($statement, $data, $this, $this->primary_key);
    return $object;
  }

  protected function get_cols ($cols)
  {
    if (is_null($cols))
    { // No columns were passed, check for a default set.
      if (isset($this->get_fields))
      { // Use default set.
        $cols = $this->get_fields;
      }
      else
      { // No default set found, return everything.
        $cols = '*';
      }
    }

    if (is_array($cols))
    { // The set of fields is an array, turn it into a string.
      $cols = join(',', $cols);
    }

    return $cols;
  }

  /** 
   * Get a single row based on the value of a field.
   */
  public function getRowByField ($field, $value, $ashash=false, $cols='*')
  {
    $cols = $this->get_cols($cols);
    $sql = "SELECT $cols FROM {$this->table} WHERE $field = :value LIMIT 1";
    $query = $this->query($sql);
    $data = array(':value'=>$value);
#    error_log("getRowByField: $sql ;".json_encode($data));
    $query->execute($data);
    $row = $query->fetch();
    if ($ashash)
      return $row;
    else
      return $this->wrapRow($row);
  }

  /** 
   * Get a single row based on the value of multiple fields.
   *
   * This is a simple AND based approach, and uses = as the comparison.
   * If you need anything more complex, write your own method, or use
   * getRowWhere() instead.
   */
  public function getRowByFields ($fields, $ashash=False, $cols='*')
  {
    $cols = $this->get_cols($cols);
    $sql = "SELECT $cols FROM {$this->table} WHERE ";
    $data = array();
    foreach ($fields as $key => $value)
    {
      if (count($data))
        $sql .= "AND ";
      $sql .= "$key = :$key ";
      $data[$key] = $value;
    }
    $sql .= " LIMIT 1";
#    error_log("getRowByFields: sql = \"$sql\" and fields = ".json_encode($data));
    $query = $this->query($sql);
    $query->execute($data);
    $row = $query->fetch();
#    error_log("Found: ".json_encode($row));
    if ($ashash)
      return $row;
    else
      return $this->wrapRow($row);
  }

  /** 
   * Get a single row, specifying the WHERE clause and bound data.
   */
  public function getRowWhere ($where, $data=array(), $ashash=False, $cols='*')
  {
    $cols = $this->get_cols($cols);
    $sql = "SELECT $cols FROM {$this->table} WHERE $where LIMIT 1";
    $query = $this->query($sql);
    $query->execute($data);
    $row = $query->fetch();
    if ($ashash)
      return $row;
    else
      return $this->wrapRow($row);
  }

  /** 
   * Get a single row based on the value of the primary key.
   */
  public function getRowById ($id, $ashash=False, $cols='*')
  {
    $cols = $this->get_cols($cols);
    return $this->getRowByField($this->primary_key, $id, $ashash, $cols);
  }

  /**
   * Return a result set using a hand crafted SQL statement.
   */
  public function listRows ($stmt, $data, $cols=Null)
  {
    $cols = $this->get_cols($cols);
    $query = "SELECT $cols FROM {$this->table} $stmt";
    return $this->execute($query, $data);
  }

  /**
   * Return a result set using a WHERE clause.
   */
  public function listWhere ($where, $data, $cols=Null)
  {
    return $this->listRows("WHERE $where", $data, $cols);
  }

  /**
   * Return a result set using a map of fields.
   */
  public function listByFields ($fields, $cols=Null, $append=Null)
  {
    $cols = $this->get_cols($cols);
    $sql = "SELECT $cols FROM {$this->table} WHERE ";
    $data = array();
    foreach ($fields as $key => $value)
    {
      if (count($data))
        $sql .= "AND ";
      $sql .= "$key = :$key ";
      $data[$key] = $value;
    }
    if (isset($append))
      $sql .= " $append";

#    error_log("sql: $sql");
#    error_log("data: ".json_encode($data));

    return $this->execute($sql, $data);
  }

  /** 
   * Return a ResultSet (or other result class) for all rows in our table.
   */
  public function all ()
  {
    $query = "SELECT * FROM {$this->table}";
    return $this->execute($query);
  }

  /** 
   * Insert a new row. 
   *
   * Note this does no checking to ensure
   * the specified fields are valid, so wrap this in your own
   * classes with more specific versions. It's also not recommended
   * that you include the primary key field, as it should be auto
   * generated by the database. To this end, by default we disallow
   * the primary key field. As the output of an insert is not consistent
   * we just return the query object, if you're at all interested.
   */
  public function newRow ($row, $opts=array())
  { // Check for options.
    if (isset($opts['table']))
      $table = $opts['table'];
    else
      $table = $this->table;
    if (isset($opts['allowpk']))
      $allowpk = $opts['allowpk'];
    else
      $allowpk = False;
    if (isset($opts['pk']))
      $pk = $opts['pk'];
    else
      $pk = $this->primary_key;

    // Now let's do this.
    $sql = "INSERT INTO $table ";
    $fieldnames = '(';
    $fieldvals  = '(';
    $fielddata  = array();
    $keys = array_keys($row);
    $kc   = count($row);
    for ($i=0; $i < $kc; $i++)
    {
      $key = $keys[$i];
      if ($key == $pk && !$allowpk) continue; // Skip primary key.
      $fieldnames .= $key;
      $fieldvals  .= ':'.$key;
      $fielddata[$key] = $row[$key];
      if ($i != $kc - 1)
      {
        $fieldnames .= ', ';
        $fieldvals  .= ', ';
      }
    }
    $fieldnames .= ')';
    $fieldvals  .= ')';
    $sql .= "$fieldnames VALUES $fieldvals";
#    error_log("newRow. sql = \"$sql\" and fields = ".json_encode($fielddata));
#    error_log("newRow.fielddata: ".json_encode($fielddata));
    $query = $this->query($sql);
    $query->execute($fielddata);
#    error_log("sterr: ".json_encode($query->errorInfo()));
#    error_log("dberr: ".json_encode($this->db->errorInfo()));
#
    if (isset($opts['return']))
    {
      $return_type = $opts['return'];
      if (isset($opts['columns']) && is_array($opts['columns']))
      {
        $fields = $opts['columns'];
      }
      else
      {
        $fields = $fielddata;
      }
      if ($return_type == $this::return_row)
      {
#        error_log("Return Row object: ".json_encode($fields));
        return $this->getRowByFields($fields);
      }
      elseif ($return_type == $this::return_raw)
      {
        return $this->getRowByFields($fields, True); 
      }
      elseif ($return_type == $this::return_key)
      {
        $rawrow = $this->getRowByFields($fields, True, $pk);
#        error_log("rawrow: ".json_encode($rawrow));
        if (isset($rawrow) && isset($rawrow[$pk]))
        {
          return $rawrow[$pk];
        }
        else
        {
          return Null;
        }
      }
    }
    return $query;
  }

  /**
   * Row count.
   */
  public function rowcount ($where=Null)
  {
    $sql = "SELECT count(id) FROM {$this->table}";
    $data = array();
    if (is_array($where) && count($where) > 0)
    {
      $sql .= ' WHERE ';
      foreach ($where as $key => $value)
      {
        if (count($data))
          $sql .= 'AND ';
        $sql .= "$key = :$key ";
        $data[$key] = $value;
      }
    }
    elseif (is_string($where))
    {
      $sql .= " WHERE $where";
    }
    $query = $this->query($sql, False);
    $query->execute($data);
    $row = $query->fetch();
    return $row[0];
  }

  /**
   * Page count.
   */
  public function pagecount ($rowcount=Null, $opts=array())
  {
    if (!is_numeric($rowcount))
    {
      $rowcount = $this->rowcount($rowcount);
    }

    if (isset($opts['count']) && $opts['count'] > 0)
      $perpage = $opts['count'];
    else
      $perpage = $this->default_page_count;

    $pages = ceil($rowcount / $perpage);

    return $pages;
  }

  /**
   * Generate ORDER BY and LIMIT statements, based on a provided sort order,
   * number of items to display per page, and what page you are currently on.
   * NOTE: pages start with 1, not 0.
   */
  public function pager ($opts=array())
  {
    if (isset($opts['sort']))
      $sort = $opts['sort'];
    else
      $sort = $this->default_sort_order;

    if (isset($opts['page']) && $opts['page'] > 0)
      $page = $opts['page'];
    else
      $page = 1;

    if (isset($opts['count']) && $opts['count'] > 0)
      $count = $opts['count'];
    else
      $count = $this->default_page_count;

    $offset = $count * ($page - 1);

    if (isset($this->sort_orders[$sort]))
    {
      $statement = "ORDER BY {$this->sort_orders[$sort]} LIMIT $offset, $count";
    }
    else
    {
      $statement = "LIMIT $offset, $count";
    }

#    error_log("pager statement: $statement");
    return $statement;
  }

  // Iterator interface to use a DBModel in a foreach loop.
  // If you attempt to use this with resultclass set to false,
  // things will break, badly. Just don't do it.

  public function rewind ()
  {
    $this->resultset = $this->all();
    return $this->resultset->rewind();
  }

  public function current ()
  {
    return $this->resultset->current();
  }

  public function next ()
  {
    return $this->resultset->next();
  }

  public function key ()
  {
    return $this->resultset->key();
  }

  public function valid ()
  {
    return $this->resultset->valid();
  }

  // ArrayAccess interface for easier querying.
  
  public function offsetGet ($offset)
  {
    return $this->getRowById($offset);
  }

  public function offsetExists ($offset)
  {
    $row = $this->getRowById($offset);
    if ($row)
      return True;
    else
      return False;
  }

  public function offsetUnset ($offset)
  {
    $sql = "DELETE FROM {$this->table} WHERE id = :id";
    $query = $this->query($sql);
    $query->execute(array(':id'=>$offset));
  }

  public function offsetSet ($offset, $value)
  {
    throw new Exception('You cannot set DB Model values that way.');
  }

  // Simple row cloning.
  public function cloneRow ($rowid)
  {
    $pk  = $this->primary_key;
    $row = $this->getRowById($rowid);
    if (isset($row))
    {
      unset($row[$pk]);
      if (is_callable(array($row, 'updateClone')))
      {
        $row->updateClone();
      }
      if (is_callable(array($row, 'save')))
      {
        $row->save();
      }
    }
    else
    {
      return Null;
    }
    return $row;
  }

} // end class Model

// End of library.
