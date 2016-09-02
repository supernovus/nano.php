<?php

namespace Nano4\DB\Schemata;

/**
 * Represents a table with version information.
 *
 * This should not be constructed manually. It is returned by the
 * Schema::getTable() and Schema::listTables() methods.
 */
class Table
{
  protected $parent;       // The parent Schemata\Tables object.
  protected $db;           // The DB object.

  /**
   * The table name.
   */
  public $name;

  /**
   * The table's current schema version.
   */
  public $current;

  /**
   * The newest schema version for this table.
   */
  public $latest;

  /**
   * Should this table be updated?
   */
  public $need_update = false;

  /**
   * Was an update performed (or attempted) in this session?
   */
  public $update_ran = false;

  /**
   * Did the update succeed? Only valid if $updated = true.
   */
  public $update_ok = false;

  /**
   * Any errors encountered when attempting an update.
   */
  public $update_err = [];

  /**
   * The output from the SQL portion of the update (if any.)
   */
  public $update_sql_output;

  /**
   * Were rows updated?
   */
  public $updated_rows = [];

  /**
   * The table schema folder.
   */
  public $tableDir;

  protected $conf; // The update configuration.

  /**
   * Build a Nano4\DB\Update\Table object.
   *
   * You should never have to call this manually, it's called by the
   * Schema class. However, in case you need to override it, the definition
   * is included here.
   *
   * @param Str      $name       The name of the table.
   * @param Object   $schemaDir  The schemata directory for this table.
   * @param Object   $parent     The parent Schema object.
   * @param Object   $db         The parent database object.
   * @param Array    $opts       Extra options (reserved for future use.)
   *
   */
  public function __construct ($name, $schemaDir, $parent, $db, $opts=[])
  {
    // Set some basics.
    $this->name = $name;
    $this->parent = $parent;
    $this->db = $db;
    $this->schemaDir = $schemaDir;

    // Get our table update directory.
    $ourDir = $schemaDir . '/' . $parent->tablesDir . '/' . $name;

    if (file_exists($ourDir))
    { // Set our update directory.
      $this->tableDir = $ourDir;

      // Get our table update configuration file.
      $ourConf = $ourDir . '/' . $parent->schemaFile;

      if (!file_exists($ourConf))
      {
        throw new \Exception(__CLASS__.": $ourConf file does not exist.");
      }
  
      $this->conf = json_decode(file_get_contents($ourConf), true);
  
      if (!$this->conf)
      {
        throw new \Exception(__CLASS__.": could not load $ourConf as JSON.");
      }
    }

    // Get the latest version of our table schema.
    $versions = $this->tableVersions();
    $latest = end($versions);
    reset($versions);
    $this->latest = $latest;

    if 
    (
      (isset($opts['checkUpdates']) && $opts['checkUpdates']) ||
      (isset($this->parent->checkUpdates) && $this->parent->checkUpdates)
    )
    {
      $this->check_table_updates();
    }
  }

  protected function ensure_db ()
  {
    if (!isset($this->db))
    {
      throw new \Exception("Cannot use version methods without a \$db");
    }
  }

  // Get the current version of our table schema.
  protected function get_table_schema ()
  {
    $this->ensure_db();
    $mdTable = $this->parent->metaTable;
    $mdName  = $this->parent->metaName;
    $mdVer   = $this->parent->metaVer;
    $metadata = $this->db->select($mdTable, 
    [
      'where'  => [$mdName=>$this->name], 
      'cols'   => $mdVer,
      'single' => true,
    ]);
    $tableExists = $this->parent->tableExists($this->name);
    if (isset($metadata, $metadata[$mdVer]) && $tableExists)
    { // Set our current version.
      $this->current = new TableVersion($metadata[$mdVer]);
    }
    elseif ($tableExists)
    { // The table exists, but there is no metadata. This is bad.
      throw new \Exception(__CLASS__.": {$this->name} is not listed in $mdTable.");
    }
    else
    { // This table is not created yet, we'll create it.
      $this->current = 0;
    }
  }

  // See if we need to be updated.
  protected function check_table_updates ()
  {
    // Get the current version of our table schema.
    if (!isset($this->current))
      $this->get_table_schema();

    if ($this->current->isNewer($this->latest))
    {
      throw new \Exception(__CLASS__.": {$this->name} schema is higher than {$this->latestVersion()}.");
    }
    elseif ($this->current->isOlder($this->latest))
    {
      $this->need_update = true;
    }
  }

  public function verColumn ()
  {
    if (isset($this->conf, $this->conf['vercolumn']))
    {
      return $this->conf['vercolumn'];
    }
    else
    {
      return $this->parent->verColumn;
    }
  }

  public function tableVersions ()
  {
    if (isset($this->conf, $this->conf["table_versions"]))
    {
      $versions = [];
      foreach ($this->conf['table_versions'] as $ver)
      {
        $version = new TableVersion($ver);
        $versions[] = $version;
      }
      return $versions;
    }
    elseif ($this->current !== 0)
    {
      return [$this->current];
    }
    else
    {
      return [new TableVersion($this->parent->defVer)];
    }
  }

  public function rowVersions ()
  {
    if (isset($this->conf, $this->conf['row_versions']))
    {
      $versions = [];
      foreach ($this->conf['row_versions'] as $ver)
      {
        $version = new RowVersion($ver);
        $versions[] = $version;
      }
      return $versions;
    }
  }

  /**
   * Perform the updates on the table schema.
   *
   * @return  Bool    It will return true if the update succeeded.
   *                  It will return false if the update failed.
   * 
   * This method will also change the values of the following properties:
   *
   *   $this->updated     Will be set to true if an update is performed.
   *   $this->update_ok   Will match the return value of this method.
   *   $this->update_err  Any errors will be appended to this list.
   *
   */
  public function update ()
  {
    $this->ensure_db();

    // See if we need to be updated.
    $this->check_table_updates();

    if (!$this->need_update) return; // If we don't need updating, go away.

    $this->need_update = false;  // Prevent it from trying again.
    $this->update_ran  = true;   // Mark that the update() was called.

    $versions = $this->tableVersions();

    foreach ($versions as $version)
    {
      $version = new TableVersion($version);
      if ($version->notNewer($this->current)) continue; // Older version.

      $sql_file = $version->sql_file;
      if (is_string($sql_file))
      {
        $sql_file = $this->tableDir . '/' . $sql_file;
      }

      // Handle requirements if they exist.
      if (isset($version->requires))
      {
        foreach ($version->requires as $rname => $rver)
        {
          $rtable = $this->parent->getTable($rname);
          if ($rver === true)
          { // We only care that the table exists.
            if ($rtable->current === 0)
            {
              $rtable->update();
            }
          }
          elseif (is_numeric($rver))
          { // We need a minimum version.
            if ($rtable->current->isOlder($rver))
            {
              $rtable->update();
            }
          }
        }
      }

      if (!isset($sql_file) || $sql_file === true)
      {
        if ($this->current === 0)
        { // The table doesn't exist, create it.
          $sql_file = $this->schemaDir . '/' . $this->name . '.sql';
        }
        else
        { // Use a default upgrade script filename.
          $ver1 = $this->current->version;
          $ver2 = $version->version;
          $sql_file = $this->tableDir . '/' . $ver1 . '-' . $ver2 . '.sql';
        }
      }

      $pre_run = $version->pre_run;
      if (isset($pre_run) && file_exists($this->tableDir.'/'.$pre_run[0]))
      {
        $this->run($pre_run[0], $pre_run[1]);
      }

      if ($sql_file && file_exists($sql_file))
      {
        $this->update_sql_output = $this->db->source($sql_file);
      }

      $post_run = $version->post_run;
      if (isset($post_run) && file_exists($this->tableDir.'/'.$post_run[0]))
      {
        $this->run($post_run[0], $post_run[1]);
      }
      $this->get_table_schema();
      $this->check_table_updates();
    }
    // TODO: actually catch and report errors.
    $this->update_ok = true;
    return true;
  }

  /**
   * This will find any rows that are outdated using the get_rows function,
   * then pass them to the appropriate functions to update them.
   */
  public function updateRows ()
  { // Find any rows that are outdated, and update them.
    $this->ensure_db();

    if (count($this->updated_rows) > 0) return; // we've already updated.

    $versions = $this->rowVersions();

    foreach ($versions as $version)
    {
      $version = new TableVersion($version);
      if ($version->notNewer($this->current)) continue; // Older version.

      // Handle requirements if they exist.
      if (isset($version->requires))
      {
        foreach ($version->requires as $rname => $rver)
        {
          $rtable = $this->parent->getTable($rname);
          if ($rver)
          { 
            if ($rtable->current === 0)
            { // Create the table.
              $rtable->update();
            }
            else
            { // Update the rows to the latest schema.
              $rtable->updateRows();
            }
          }
        }
      }

      if (isset($this->conf, $this->conf['get-rows']))
      {
        $getrows = $this->conf['get-rows'];
      }
      elseif (isset($this->parent->getRows))
      {
        $getrows = $this->parent->getRows;
      }
  
      $rows = null;
      if (is_callable($getrows))
      {
        $rows = $getrows($this);
      }
      elseif (is_array($getrows) && file_exists($this->tableDir.'/'.$getrows[0]))
      {
        $rows = $this->run($getrows[0], $getrows[1]);
      }

      if (isset($rows))
      {
        $run = $version->run;
        if (isset($run) && file_exists($this->tableDir.'/'.$run[0]))
        {
          $updated = $this->run($run[0], $run[1]);
          if (isset($updated) && is_array($updated) && count($updated) > 0)
          {
            $this->updated_rows = array_merge($this->updated_rows, $updated);
          }
        }
      }
    }
    return $this->updated_rows;
  }

  public function run ($filename, $funcname, $params=null)
  {
    $class = $this->tableDir.'/'.$filename;
    require_once "$class";
    $ns = $this->parent->updateNamespace;
    $func = "\\$ns\\".$funcname;
    if (is_callable($func))
    {
      return $func($this, $params);
    }
  }

  public function currentVersion ()
  {
    if (isset($this->current))
      return $this->current->version;
  }

  public function latestVersion ()
  {
    if (isset($this->latest))
      return $this->latest->version;
  }

  public function hasTag ($tag)
  {
    if (isset($this->conf, $this->conf['tags']) && is_array($this->conf['tags']) && in_array($tag, $this->conf['tags']))
    {
      return true;
    }
    return false;
  }

  public function needs ()
  {
    if (isset($this->conf, $this->conf['needs']))
    {
      return $this->conf['needs'];
    }
  }

  public function wants ()
  {
    if (isset($this->conf, $this->conf['wants']))
    {
      return $this->conf['wants'];
    }
  }

  public function depends ()
  {
    $deps = [];
    if (isset($this->conf, $this->conf['needs']))
    {
      foreach ($this->conf['needs'] as $need)
      {
        $deps[$need] = true;
      }
    }
    if (isset($this->conf, $this->conf['wants']))
    {
      foreach ($this->conf['wants'] as $want)
      {
        $deps[$want] = false;
      }
    }
    return $deps;
  }

  public function getDB ()
  {
    return $this->db;
  }

  public function getParent ()
  {
    return $this->parent;
  }

}

abstract class Version
{
  public $version;
  public $requires;

  public function __construct ($def)
  {
    if (is_array($def) || is_object($def))
    {
      foreach ($def as $propname => $propval)
      {
        $propname = str_replace('-','_', $propname);
        if (property_exists($this, $propname))
        {
          $this->$propname = $propval;
        }
      }
    }
    if (!isset($this->version))
    {
      throw new \Exception("No version property was found in: ".json_encode($def));
    }
  }

  public function compare ($checkVer, $operator=null)
  {
    if (is_object($checkVer))
      $checkVer = $checkVer->version;
    return version_compare($this->version, $checkVer, $operator);
  }

  public function notNewer ($checkVer)
  {
    return $this->compare($checkVer, '<=');
  }

  public function isOlder ($checkVer)
  {
    return $this->compare($checkVer, '<');
  }

  public function isNewer ($checkVer)
  {
    return $this->compare($checkVer, '>');
  }

  public function notOlder ($checkVer)
  {
    return $this->compare($checkVer, '>=');
  }

}

class TableVersion extends Version
{
  public $pre_run;
  public $post_run;
  public $sql_file = true;

  public function __construct ($verdef)
  {
    if (is_string($verdef) )
    {
      $this->version = $verdef;
    }
    elseif (is_numeric($verdef))
    {
      if (fmod(floatval($verdef), 1) == 0)
        $this->version = (string)$verdef . '.0';
      else
        $this->version = (string)$verdef;
    }
    else
    {
      parent::__construct($verdef);
    }
  }
}

class RowVersion extends Version
{
  public $run;
}

