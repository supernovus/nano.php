<?php

namespace Nano\DB\Schemata;

use \Nano\DB\PDO\Simple;

/**
 * Database Schema Management class.
 *
 * This class expects that you have a special database table that stores the
 * current schema version of each table. Your initial table creation should
 * inject the current version of the schema into the metadata table.
 */
class Tables
{
  /**
   * The database object.
   */
  protected $db;

  /**
   * The directories where schema files are found.
   */
  protected $schemaDirs = [];

  /**
   * The namespace for update scripts.
   */
  public $updateNamespace = 'UpdateSchema';

  /**
   * The sub-directory of $schemaDir where table definitions are found.
   */
  public $tablesDir = 'tables';

  /**
   * The sub-directory of $schemaDir where current SQL files are found.
   */
  public $sqlDir = 'sql';

  /**
   * The sub-directory of $tablesDir/table where update SQL files are found.
   * If not set, the SQL files must be in the $tablesDir/table folder.
   */
  public $tablesSQL;

  /**
   * The sub-directory of $tablesDir/table where update PHP scripts are found.
   * If not set, the PHP files must be in the $tablesDir/table folder.
   */
  public $tablesPHP;

  /**
   * The name of the table schema file.
   */
  public $schemaFile = 'schema.json';

  /**
   * The name of the schema metadata table.
   */
  public $metaTable = 'schema_metadata';

  /**
   * The name of the column containing the table name in the $metaTable.
   */
  public $metaName = 'name';

  /**
   * The name of the column containing the schema version in the $metaTable.
   */
  public $metaVer = 'version';

  /**
   * The name of the version column in individual rows.
   */
  public $verColumn = 'version';

  /**
   * The default version if no updates are found.
   */
  public $defVer = '1.0';

  /**
   * Force a check of updates on Table initialization?
   */
  public $checkUpdates = false;

  /**
   * Provide a default function for retrieving outdated rows from a table.
   */
  public $getRows;

  protected $tables = [];                 // A cache of loaded tables.

  /**
   * Build a Nano\DB\Schemata\Tables object.
   *
   * @param Object $db          The database object instance.
   * @param Array  $opts        Any extra options.
   * @param Array  $schemaDirs  Initial schema directories to scan.
   *
   * The $db object must be a subclass of Nano\DB\PDO\Simple object that
   * includes the Nano\DB\PDO\Simple\NativeDB trait.
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
   *                      Defaults to "tables".
   *
   *   "schema_file"      The update config file for each table.
   *                      Defaults to "schema.json".
   *
   *   "sql_dir"          The sub-folder of the schema folder which contains
   *                      the SQL files used to build our initial tables.
   *                      Defaults to "sql".
   *
   *   "default_version"  The default version if no updates exist.
   *                      Defaults to "1.0".
   *
   *   "get_rows"         A callable that will be used as the default
   *                      implementation to look for rows. It's signature is:
   *                      ($table);
   *                      It should return an array of rows whose version is
   *                      older than the latest version. The rows should be 
   *                      in whatever format is used by your update scripts.
   *                      See addDir() for details on row update scripts.
   *
   *   "check_updates"    If set to true, an update check will be forced
   *                      on each of the Tables for listing purposes.
   *
   * If the $schemaDirs parameter is passed, it will be sent to the
   * addDir() method, see below.
   *
   */
  public function __construct ($db, $opts=[], $dirs=null)
  {
    if (isset($db))
    {
      if (!($db instanceof Simple))
      {
        throw new \Exception(__CLASS__.": \$db must be a sub-class of \\Nano\\DB\\PDO\\Simple");
      }
      if (!is_callable([$db, 'source']))
      {
        throw new \Exception(__CLASS__.": \$db must use \\Nano\\DB\\PDO\\Simple\\NativeDB trait");
      }
  
      $this->db = $db;
    }

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
    if (isset($opts['sql_dir']))
    {
      $this->sqlDir = $opts['sql_dir'];
    }
    if (isset($opts['get_rows']))
    {
      $this->getRows = $opts['get_rows'];
    }
    if (isset($opts['check_updates']))
    {
      $this->checkUpdates = $opts['check_updates'];
    }

    if (isset($this->db) && !$this->tableExists($this->metaTable))
    {
      throw new \Exception(__CLASS__.": no {$this->metaTable} table found.");
    }

    if (isset($dirs))
    {
      $this->addDir($dirs);
    }
  }

  /**
   * Add a schemata directory to our list.
   *
   * @param mixed $schemaDir  The path to a directory containing schema files.
   *                          This can be an array of paths to scan.
   *
   * The $schemaDir must contain at least the $sqlDir and $tablesDir
   * subdirectories.
   *
   * The $sqlDir must have the current version of each table as $tablename.sql.
   *
   * The $schemaDir must contain a folder for for each table, and a schema.json
   * file within that, which at the very least must contain a property called 
   * "table-versions" which must be set to an array.
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
   *               namespace, and signature is: ($table);
   *
   *   "post-run"  The same syntax as "pre-run", except if defined this is
   *               called after the update SQL for this table has been called.
   *
   *   "sql-file"  If specified, it overrides the usual $oldver-$newver.sql
   *               filename. If set to false we skip the SQL step (only useful
   *               if the pre-run or post-run PHP scripts perform all the
   *               changes required.)
   *
   * In addition to "table-versions" the schema.json also supports the 
   * following optional properties:
   *
   *   "needs"         An array of tables required by this table.
   *
   *   "wants"         Similar to needs, but will continue if the table is
   *                   not found in the current set of tables.
   *
   *   "row-versions"  Similar to the "table-versions" property, except instead
   *                   of being on a table basis, it's for individual rows.
   *                   It looks for a version column and compares it against
   *                   the versions in the "row_versions" array.
   *                   Each version definition must be an object, and supports
   *                   the following properties:
   *
   *                    "version"   The version string, this is required.
   *                    
   *                    "requires"  This is similar to the same property in 
   *                                the "table_versions" definitions.
   *                                However the tables only need to be true.
   *                                Specific versions aren't checked here.
   *
   *                    "run"       The same synax as "pre-run" or "post-run".
   *                                There is only one run command as row-based
   *                                updates don't support SQL scripts.
   *                                The signature is slightly different:
   *                                ($table, ['rows'=>$rows]);
   *                                The 'rows' parameter contains the rows that
   *                                are being processed.
   *
   *   "vercolumn"     Override the version column name for this table.
   *
   *   "get-rows"      Override the "get_rows" definition if it was set.
   *                   This is an array in the same syntax of "pre-run".
   *                   The signature is described in the "get_rows" option
   *                   of the __construct() method.
   *
   *   "tags"          A flat array of application-specific tags.
   *
   *   "options"       An object of application-specific options.
   *
   *   "replaced-by"   The name of the table which replaces this one.
   *                   If this is set, then the table won't be created if
   *                   it does not exist. The new table is expected to replace
   *                   it entirely.
   *
   *   "replaces"      The name oa the table this replaced.
   *
   *  If "replaces" is set, then any of the following can be used:
   *
   *   "replacement-sql"    The name of a script to replace the old table.
   *                        As with "sql-file" it can be set to false to skip
   *                        the SQL step entirely. If not specified, the
   *                        default name is: 'replace_$oldtable.sql' where
   *                        $oldtable is the name of the old table.
   *
   *   "replacement-pre-run"  The same as "pre-run" in a version.
   *
   *   "replacement-post-run" The same as "post-run" in a version.
   *
   * Note: In any of the names above with a hyphen (-) the underscore (_)
   *       can be used instead. So 'table_versions' == 'table-versions'.
   */
  public function addDir ($dir)
  {
    if (is_array($dir))
    {
      foreach ($dir as $sdir)
      {
        $this->addDir($sdir);
      }
    }
    elseif (is_string($dir))
    {
      if (trim($dir) === '') return;
      if (file_exists($dir . '/' . $this->tablesDir))
      {
        $this->schemaDirs[] = $dir;
      }
    }
  }

  /**
   * Scan all of our tables, and return an unsorted list.
   *
   * @param  Array $opts  Any options to pass to the Table class (optional.)
   *
   * @return Array        An array of Table objects.
   *
   */
  public function scanTables ($opts=[])
  {
    $tables = [];
    foreach ($this->schemaDirs as $schemaDir)
    {
      $dir = $schemaDir . '/' . $this->tablesDir;
      if (file_exists($dir))
      {
        $table_names = scandir($dir);
        foreach ($table_names as $name)
        {
          if (substr($name,0,1) == '.') continue; // skip dots.
          $this->tables[$name] = $tables[] = 
            new Table($name, $schemaDir, $this, $this->db, $opts);
        }
      }
    }
    return $tables;
  }

  /**
   * Return a sorted list of tables.
   */
  public function listTables ($opts=[])
  {
    if (count($this->tables) === 0)
    {
      $tableOpts = isset($opts['table_options']) ? $opts['table_options'] : [];
      $tables = $this->scanTables($tableOpts);
    }
    else
    {
      $tables = array_values($this->tables);
    }
    $list = [];
    $seen = [];
    foreach ($tables as $table)
    {
      $this->sortTables($table, $list, $seen, $opts);
    }
    return $list;
  }

  private function sortTables ($table, &$list, &$seen, $opts)
  {
    if (isset($seen[$table->name])) return; // already seen.
    $tags = null;
    $allTags = false;
    if (isset($opts['hasTag']))
    { // List only tables containing a certain tag, or tags.
      $tags = $opts['hasTag'];
    }
    elseif (isset($opts['allTags']))
    {
      $tags = $opts['allTags'];
      $allTags = true;
    }
    if (isset($tags))
    {
      if (!is_array($tags))
        $tags = [$tags];
      foreach ($tags as $tag)
      {
        if (!$table->hasTag($tag)) return;
        if (!$alltags) break;
      }
    }

    $depends = $table->depends();

    if (count($depends) > 0)
    {
      foreach ($depends as $dep => $need)
      {
        if (isset($this->tables[$dep]))
        {
          $this->sortTables($this->tables[$dep], $list, $seen, $opts);
        }
        elseif ($need)
        {
          throw new \Exception("required table '$dep' not found!");
        }
      }
    }

    $list[] = $table;
    $seen[$table->name] = true;
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
      foreach ($this->schemaDirs as $dir)
      {
        if (file_exists("$dir/".$this->tablesDir."/$name/".$this->schemaFile))
        {
          return $this->tables[$name] = 
            new Table($name, $dir, $this, $this->db, $opts);
        }
      }
    }
  }

  /**
   * Update all tables that are out of date.
   *
   * @return Array         An array of Table objects that were updated.
   */
  public function updateAllTables ($opts=[])
  {
    $tables = $this->listTables($opts); // Get all updates.
    $updated = [];
    foreach ($tables as $table)
    {
      if ($table->need_update)
      {
        $table->update();
        $updated[] = $table;
      }
    }
    return $updated;
  }

  /**
   * Update all rows that are out of date.
   *
   * @return Array     An array of Table objects that had rows updated.
   */
  public function updateAllRows ($opts=[])
  {
    $tables = $this->listTables($opts);
    $updated = [];
    foreach ($tables as $table)
    {
      $rows = $table->updateRows();
      if (count($rows) > 0)
      {
        $updated[] = $table;
      }
    }
    return $updated;
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
    if (!isset($this->db))
    {
      throw new \Exception("Cannot use tableExists() without a \$db");
    }
    $stmt = $this->db->db->prepare("SHOW TABLES LIKE :name");
    $stmt->execute(["name"=>$name]);
    $rows = $stmt->fetchAll();
    if (count($rows) > 0)
    {
      return true;
    }
    return false;
  }

  /**
   * Return our database instance.
   */
  public function getDB ()
  {
    return $this->db;
  }

}

