<?php

namespace Nano4\DB;

/**
 * A quick function to map SQL fields to placeholders.
 */
function map_fields ($fields)
{
  return array_map(function ($field)
  {
    return "$field = :$field";
  }, $fields);
}

/**
 * A comparitor for NULL values.
 */
function map_nulls ($fields, $not=false)
{
  $is = "NULL";
  if ($not)
    $is = "NOT $is";
  return array_map(function ($field) use ($is)
  {
    return "$field IS $is";
  }, $fields);
}

/**
 * Display a statement error
 */
function db_error_log ($object)
{
  $code = $object->errorCode();
  if ($code != '00000' && $code != '')
  {
    error_log("DB error: ".json_encode($object->errorInfo()));
  }
}

/**
 * Get a query property from an object.
 */
function get_query_property($object, $prop)
{
  $prop_func = "get_$prop";
  if (is_callable([$object, $prop_func]))
    return $object->$prop_func();
  elseif (isset($object->$prop))
    return $object->$prop;
  elseif ($object instanceof \ArrayAccess && isset($object[$prop]))
    return $object[$prop];
  else
    return null;
}

/**
 * A simple, lightweight DB class.
 */
class Simple
{
  /**
   * The PDO database object.
   */
  public $db;

  /**
   * The database name, extracted from the DSN.
   */
  public $name;

  /**
   * The server id (optional)
   */
  public $server_id;

  /**
   * The database configuration, if 'keep_config' is true.
   * It's a protected property, so only this class can use it.
   */
  protected $db_conf;

  /**
   * Build our DB\Simple object.
   *
   * @param Mixed $conf          The database configuration.
   * @param Bool  $keep_config   Save the database configuration.
   *                             Default: false.
   *
   * The $conf may be either a JSON string, a JSON filename, or an Array.
   * The Array must contain at least a 'dsn' member. If the database uses
   * a username and password, it must also contain 'user' and 'pass'
   * members. If the database has a unique server_id, then it should
   * be specified as the 'sid' member.
   *
   * The database name will be extracted from the DSN either as the
   * 'dbname' property, or as the SQLite filename.
   *
   * If $keep_config is true, then the configuration is stored in the
   * protected $db_conf property.
   */
  public function __construct ($conf, $keep_config=false)
  {
    if (is_string($conf))
    {
      if (strpos($conf, '{') === False)
      { // No { symbol, we assume it's a filename.
        $conf = file_get_contents($conf);
      }
      $conf = json_decode($conf, true);
    }

    if (is_array($conf) && isset($conf['dsn']))
    {
      if (isset($conf['user']) && isset($conf['pass']))
        $this->db = new \PDO($conf['dsn'], $conf['user'], $conf['pass']);
      else
        $this->db = new \PDO($conf['dsn']);

      if (isset($conf['sid']))
        $this->server_id = $conf['sid'];

      $matches = [];
      if (preg_match('/dbname=(\w+)/', $conf['dsn'], $matches))
        $this->name = $matches[1];
      elseif (preg_match('/sqlite:(\w+)/', $conf['dsn'], $matches))
        $this->name = $matches[1];

      if ($keep_config)
      {
        $this->db_conf = $conf;
      }
    }
    else
    {
      throw new \Exception(__CLASS__.": invalid database configuration");
    }    
  }

  /**
   * Perform a SELECT query.
   *
   * @param mixed $table  The database table(s) we are querying against.
   * @param mixed $opts   (Optional) An associative array of options.
   *
   * The $table may be either a String, or an Array. If it is an Array, the
   * tables will be joined using a comma.
   *
   * The $opts can contain any of the following optional parameters:
   *
   *  'where'    The WHERE statement. Can be a string or associative array.
   *
   *  'data'     If 'where' is a string, this contains the placeholder data.
   *             This is not used at all if 'where' is an associative array.
   *
   *  'cols'     The columns to return. Defaults to '*'.
   *
   *  'order'    The ORDER BY statement: e.g. "timestamp DESC, name ASC"
   *
   *  'limit'    The LIMIT statement.
   *
   *  'offset'   The OFFSET statement (only used with 'limit').
   *
   *  'single'   If set to true, it explicitly sets LIMIT 1, and returns
   *             the row data rather than the statement object.
   *
   *  'fetch'    Change the PDO Fetch Mode. Default: PDO::FETCH_ASSOC.
   *
   * The 'where', 'cols', 'order', and 'limit' parameters can be Objects
   * with either properties of the same name within them, or a get_$prop()
   * method to return the value.
   *
   * Alternatively, the entire $opts structure can be such an Object, and all
   * options will be derived from the object properties or methods.
   *
   */
  public function select ($table, $opts=[])
  {
    if (is_array($table))
    {
      $table = join(',', $table);
    }

    if (is_object($opts))
    {
      $query = $opts;
      $opts = [];
      $pval = get_query_property('where');
      if (isset($pval) && is_array($pval) && count($pval) == 2)
      {
        $opts['where'] = $pval[0];
        $opts['data']  = $pval[1];
      }
      foreach (['cols','order','limit','offset','single','fetch'] as $prop)
      {
        $pval = get_query_property($query, $prop);
        if (isset($pval))
        {
          $opts[$prop] = $pval;
        }
      }
    }

    $cols = '*';
    if (isset($opts['cols']))
    {
      if (is_array($opts['cols']))
      {
        $cols = json(',', $cols);
      }
      elseif (is_string($opts['cols']))
      {
        $cols = $opts['cols'];
      }
      elseif (is_object($opts['cols']))
      {
        $pval = get_query_property($opts['cols'], 'cols');
        if (isset($pval))
          $cols = $pval;
      }
    }

    $sql = "SELECT $cols FROM $table";
    
    $data = null;

    if (isset($opts['where']))
    {
      $sql .= " WHERE ";
      if (is_array($opts['where']))
      {
        $nulls = array_keys($opts['where'], null, true);
        $data = $opts['where'];
        foreach ($nulls as $null)
        {
          unset($data[$null]);
        }
        $keys  = array_keys($data);
        $haskeys = false;
        if (count($keys) > 0)
        {
          $haskeys = true;
          $where = map_fields($keys);
          $sql  .= join(" AND ", $where);
        }
        if (count($nulls) > 0)
        {
          if ($haskeys)
            $sql .= ' AND ';
          $where = map_nulls($nulls);
          $sql  .= join(" AND ", $where);
        }
      }
      elseif (is_string($opts['where']) && isset($opts['data']))
      {
        $data = $opts['data'];
        $sql .= $opts['where'];
      }
      elseif (is_object($opts['where']))
      {
        $pval = get_query_property($opts['where'], 'where');
        if (isset($pval) && is_array($pval) && count($pval) == 2)
        {
          $sql .= $pval[0];
          $data = $pval[1];
        }
        else
        {
          throw new \Exception(__CLASS__.": invalid WHERE object in select()");
        }
      }
      else
      {
        throw new \Exception(__CLASS__.": invalid WHERE clause in select()");
      }
    }

    if (isset($opts['order']))
    {
      $sql .= " ORDER BY ";
      if (is_string($opts['order']))
      {
        $sql .= $opts['order'];
      }
      elseif (is_array($opts['order']))
      {
        $sql .= join(',', $opts['order']);
      }
      elseif (is_object($opts['order']))
      {
        $pval = get_query_propery($opts['order'], 'order');
        if (isset($pval))
        {
          $sql .= $pval;
        }
        else
        {
          throw new \Exception(__CLASS__.": invalid ORDER object in select()");
        }
      }
      else
      {
        throw new \Exception(__CLASS__.": invalid ORDER clause in select()");
      }
    }

    if (isset($opts['single']) && $opts['single'])
    {
      $sql .= " LIMIT 1";
    }
    elseif (isset($opts['limit']))
    {
      if (is_object($opts['limit']))
      {
        $pval = get_query_property($opts['limit'], 'limit');
        if (isset($pval))
        {
          $opts['limit'] = $pval;
        }
        $pval = get_query_property($opts['limit'], 'offset');
        if (isset($pval))
        {
          $opts['offset'] = $pval;
        }
      }
      if (is_string($opts['limit']))
      {
        $sql .= " LIMIT " . $opts['limit'];
        if (isset($opts['offset']))
        {
          $sql .= " OFFSET " . $opts['offset'];
        }
      }
      elseif (is_array($opts['limit']))
      {
        $pval = $opts['limit'];
        $sql .= " LIMIT ". $pval[0] . " OFFSET " . $pval[1];
      }
    }

    if (isset($opts['fetch']))
    {
      $fetch_mode = $opts['fetch'];
    }
    else
    {
      $fetch_mode = \PDO::FETCH_ASSOC;
    }

#    error_log("SQL: $sql");
#    error_log("Data: ".json_encode($data));

    $stmt = $this->db->prepare($sql);
    if (isset($fetch_mode) && ! is_bool($fetch_mode))
    {
      $stmt->setFetchMode($fetch_mode);
    }
    $stmt->execute($data);

    db_error_log($stmt);

    if (isset($opts['single']) && $opts['single'])
    {
      return $stmt->fetch();
    }
    else
    {
      return $stmt;
    }
  }

  /**
   * Create a new row.
   *
   * @param String $table  The table to insert the data into.
   * @param Array  $data   An associative array of data we're inserting.
   */
  public function insert ($table, $data)
  {
    $flist = $dlist = null;
    if (is_object($data))
    {
      $flist = get_query_property($data, 'fields');
      $dlist = get_query_property($data, 'values');
      if (!isset($flist, $dlist))
      {
        $pval = get_query_property($data, 'data');
        if (isset($pval))
        {
          $data = $pval;
        }
        else
        {
          throw new \Exception(__CLASS__.": invalid object in insert()");
        }
      }
    }
    if (!isset($flist, $dlist))
    {
      $fnames = array_keys($data);
      $flist = join(",", $fnames);
      $dnames = array_map(function ($val)
      {
        return ":$val";
      }, $fnames);
      $dlist = join(",", $dnames);
    }

    $sql = "INSERT INTO $table ($flist) VALUES ($dlist)";

#    error_log("INSERT SQL: $sql");
#    error_log("INSERT data: ".json_encode($data));

    $stmt = $this->db->prepare($sql);
    $stmt->execute($data);

    db_error_log($stmt);

    return $stmt;
  }

  /**
   * Save an existing row.
   *
   * @param String $table  The table to insert the data into.
   * @param Mixed  $where  The WHERE statement.
   * @param Array  $cdata  The columns we are updating.
   * @param Array  $wdata  The WHERE placeholder data (see below.)
   *
   * If $where is an Array, then it's a standalone WHERE clause, and
   * the $wdata parameter is not needed (and will be ignored.)
   *
   * If $where is a string, then $wdata must contain the placeholder
   * data for the WHERE statement.
   */
  public function update ($table, $where, $cdata=null, $wdata=null)
  {
    if (is_object($where))
    {
      $query = $where;
      $pval = get_query_property($query, 'where');
      if (isset($pval))
      {
        $where = $pval[0];
        $wdata = $pval[1];
      }
      else
      {
        throw new \Exception(__CLASS__.": invalid WHERE object in update()");
      }
      $pval = get_query_property($query, 'data');
      if (isset($pval))
      {
        $cdata = $pval;
      }
    }
    if (is_object($cdata))
    {
      $pval = get_query_property($cdata, 'data');
      if (isset($pval))
      {
        $cdata = $pval;
      }
      else
      {
        throw new \Exception(__CLASS__.": invalid data object in update()");
      }
    }

    if (!is_array($cdata))
    {
      throw new \Exception(__CLASS__.": invalid cdata passed to update()");
    }

    $set = join(",", map_fields(array_keys($cdata)));

    if (is_array($where))
    {
      $data = $where + $cdata;
      $where = join(" AND ", map_fields(array_keys($where)));
    }
    elseif (is_string($where) && isset($wdata))
    {
      $data = $wdata + $cdata;
    }
    else
    {
      throw new \Exception(__CLASS__.": invalid WHERE clause in update()"); 
    }
  
    $sql = "UPDATE $table SET $set WHERE $where";
  
    $stmt = $this->db->prepare($sql);
    $stmt->execute($data);

    db_error_log($stmt);

    return $stmt;
  }

  /**
   * Delete an existing row.
   *
   * @param string $table  The table to delete from.
   * @param Mixed  $where  The WHERE statement.
   * @param Array  $wdata  The WHERE placeholder data (same as update.)
   *
   */
  public function delete ($table, $where, $wdata=null)
  {
    if (is_object($where))
    {
      $pval = get_query_property($where, 'where');
      if (isset($pval))
      {
        $where = $pval[0];
        $wdata = $pval[1];
      }
      else
      {
        throw new \Exception(__CLASS__.": invalid WHERE object in delete()");
      }
    }
    elseif (is_array($where))
    {
      $wdata = $where;
      $where = join(" AND ", map_fields(array_keys($where)));
    }
    elseif (!is_string($where))
    {
      throw new \Exception(__CLASS__.": invalid WHERE clause in delete()");
    }

    $sql = "DELETE FROM $table WHERE $where";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($wdata);

    db_error_log($stmt);

    return $stmt;
  }

}
