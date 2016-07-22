<?php

namespace Nano4\DB\Schemata;

use \Nano4\DB\Simple;

/**
 * Database Schema Management class.
 *
 * This class expects that you have a special database table that stores the
 * current schema version of each table. Your initial table creation should
 * inject the current version of the schema into the metadata table.
 */
class Tables
{
  protected $db; // The DB object.

  /**
   * The directory where schema files are found.
   */
  public $schemaDir;

  /**
   * The namespace for update scripts.
   */
  public $updateNamespace = 'UpdateSchema';

  /**
   * The sub-directory of $schemaDir where update folders are found.
   */
  public $tablesDir  = 'tables';

  /**
   * The sub-directory of $schemaDir where current SQL files are found.
   */
  public $sqlDir = 'sql';

  /**
   * The name of the update configuration file.
   */
  public $schemaFile = 'update.json';

  /**
   * The name of the schema metadata table.
   */
  public $metaTable  = 'schema_metadata';

  /**
   * The name of the column containing the table name in the $metaTable.
   */
  public $metaName   = 'name';

  /**
   * The name of the column containing the schema version in the $metaTable.
   */
  public $metaVer    = 'version';

  /**
   * The name of the version column in individual rows.
   */
  public $verColumn = 'version';

  /**
   * The default version if no updates are found.
   */
  public $defVer     = '1.0';

  /**
   * Are we storing versions as text or numbers?
   */
  public $verIsText = true;

  protected $tables = [];                 // A cache of loaded tables.

  /**
   * Build a Nano4\DB\Update\Schema object.
   *
   * @param Object $db          The database object instance.
   * @param String $schemaDir   The directory with the schema files.
   * @param Array  $opts        Any extra options.
   *
   * The $db object must be a subclass of Nano4\DB\Simple object that
   * includes the Nano4\DB\Simple\NativeDB trait.
   *
   * The $schemaDir must contain at least the $sqlDir and $tablesDir
   * subdirectories.
   *
   * The $sqlDir must have the current version of each table as $tablename.sql.
   *
   * The $schemaDir must contain a folder for for each table, and a schema.json
   * file within that, which at the very least must contain a property called 
   * "table_versions" which must be set to an array.
   *
   * Each member of the array can be either a version string, 
   * or an object. If it is a string, we assume a file called 
   * $oldver-$newver.sql exists, which will be called to perform the update. 
   *
   * If it is an object, then it requires a property called "version" 
   * which should be a version string, and can also have the following 
   * optional properties:
   *
   *   "requires"  An object where each property key is the name of a table
   *               and each property value is the version of that table that
   *               is required before we can perform the update. If the value
   *               is specified as true instead of a version number, then the
   *               version doesn't matter, and the mere existence of the
   *               table will suffice.
   *
   *   "pre-run"   An array of ["filename", "function_name"] where the filename
   *               is a .php file in the relative folder, and the function
   *               name is a function to call after requirements have been
   *               fulfilled, but before the update SQL for this table has
   *               been called. The SQL file should use the UpdateSchema
   *               namespace, and the function is called function($db, $this)
   *               where $this is the UpdateSchema object instance.
   *
   *   "post-run"  The same syntax as "pre-run", except if defined this is
   *               called after the update SQL for this table has been called.
   *
   *   "sql-file"  If specified, it overrides the usual $oldver-$newver.sql
   *               filename. If set to false we skip the SQL step (only useful
   *               if the pre-run or post-run PHP scripts perform all the
   *               changes required.)
   *
   * In addition to "table_versions" the schema.json also supports the 
   * following optional properties:
   *
   *   "needs"         An array of tables required by this table.
   *
   *   "row_versions"  Similar to the "table_versions" property, except instead
   *                   of being on a table basis, it's for individual rows.
   *                   It looks for a version column and compares it against
   *                   the versions in the "row_versions" array.
   *                   Each version definition must be an object, and supports
   *                   the following properties:
   *                    
   *                    "requires"  Exactly the same as the same property in
   *                                the "table_versions" definitions.
   *
   *                    "run"       The same synax as "pre-run" or "post-run".
   *                                There is only one run command as row-based
   *                                updates don't support SQL scripts.
   *
   *
   *   "vercolumn"     Override the version column name for this table.
   *
   *   "tags"          A flat array of application-specific tags.
   *
   *   "options"       An object of application-specific options.
   *
   * The $opts can override defaults, and specify custom behaviours.
   *
   *   "metadata_table"   Override the name of the table containing the
   *                      current schema version for every application table.
   *                      Defaults to "schema_metadata".
   *
   *   "metadata_name"    Override the name of the column containing the
   *                      table name within the metadata table.
   *                      Defaults to "name".
   *
   *   "metadata_version" Override the name of the column containing the
   *                      schema version within the metadata table.
   *                      Defaults to "version".
   *
   *   "version_column"   Overrides the name of the default version column
   *                      in tables using the "row_versions" feature.
   *                      Defaults to "version".
   *
   *   "tables_dir"       The sub-folder of the schema folder that contains
   *                      the list of updated tables. 
   *                      Defaults to "updates".
   *
   *   "schema_file"      The update config file for each table.
   *                      Defaults to "update.json".
   *
   *   "default_version"  The default version if no updates exist.
   *                      Defaults to "1.0".
   *
   */
  public function __construct ($db, $schemaDir, $opts=[])
  {
    if (!($db instanceof Simple))
    {
      throw new \Exception(__CLASS__.": \$db must be a sub-class of \\Nano4\\DB\\Simple");
    }
    if (!is_callable([$db, 'source']))
    {
      throw new \Exception(__CLASS__.": \$db must use \\Nano4\\DB\\Simple\\NativeDB trait");
    }
    if (!$schemaDir || ! file_exists($schemaDir))
    {
      throw new \Exception(__CLASS__.": \$schemaDir does not exist.");
    }

    $this->db = $db;
    $this->schemaDir = $schemaDir;

    if (isset($opts['tables_dir']))
    {
      $this->tablesDir = $opts['tables_dir'];
    }
    if (isset($opts['schema_file']))
    {
      $this->schemaFile = $opts['schema_file'];
    }
    if (isset($opts['metadata_table']))
    {
      $this->metaTable = $opts['metadata_table'];
    }
    if (isset($opts['metadata_name']))
    {
      $this->metaName = $opts['metadata_name'];
    }
    if (isset($opts['metadata_version']))
    {
      $this->metaVer = $opts['metadata_version'];
    }
    if (isset($opts['default_version']))
    {
      $this->defVer = $opts['default_version'];
    }

    if (! file_exists($schemaDir . '/' . $this->tablesDir))
    {
      throw new \Exception(__CLASS__.": no {$this->tablesDir} folder found.");
    }

    if (!$this->tableExists($this->metaTable))
    {
      throw new \Exception(__CLASS__.": no {$this->metaTable} table found.");
    }
  }

  /**
   * Get a list of any tables with updates.
   *
   * @param  Array $opts  Any options to pass to the Table class (optional.)
   *
   * @return Array        An array of Table objects.
   *
   */
  public function listTables ($opts=[])
  {
    $dir = $this->schemaDir . '/' . $this->tablesDir;
    $table_names = scandir($dir);
    $tables = [];
    foreach ($table_names as $name)
    {
      if (substr($name,0,1) == '.') continue; // skip dots.
      $this->tables[$name] = $tables[] = 
        new Table($name, $this, $this->db, $opts);
    }
    return $tables;
  }

  /**
   * Get a Table object for the specified table.
   *
   * @param  Str    $name    The name of the table.
   * @param  Array  $opts    Any options to pass to the Table class (optional.)
   *
   * @return Table           The table object.
   *
   */
  public function getTable ($name, $opts=[])
  {
    if (isset($this->tables[$name]))
    {
      return $this->tables[$name];
    }
    else
    {
      return $this->tables[$name] = new Table($name, $this, $this->db, $opts);
    }
  }

  /**
   * Update all tables that are out of date.
   *
   * @param  Array  $opts  Any options to pass to the Table class (optional.)
   *
   * @return Array         An array of Table objects.
   */
  public function updateAll ($opts=[])
  {
    $tables = $this->listTables(); // Get all updates.
    foreach ($tables as $table)
    {
      if ($table->need_update)
      {
        $table->update();
      }
    }
    return $this->tables;
  }

  /**
   * See if a database table exists.
   *
   * @param  Str  $name   The name of the table to look for.
   *
   * @return Bool         Returns true if the table exists.
   */
  public function tableExists ($name)
  {
    $stmt = $this->db->db->prepare("SHOW TABLES LIKE :name");
    $stmt->execute(["name"=>$name]);
    $rows = $stmt->fetchAll();
    if (count($rows) > 0)
    {
      return true;
    }
    return false;
  }

}

