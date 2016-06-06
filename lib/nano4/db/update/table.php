<?php

namespace Nano4\DB\Update;

/**
 * Represents a table with update information.
 *
 * This should not be constructed manually. It is returned by the
 * Schema::getTable() and Schema::listTables() methods.
 */
class Table
{
  protected $parent;       // The parent UpdateSchema object.
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
  public $update_run = false;

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
   * The table update folder.
   */
  public $updateDir;

  protected $conf; // The update configuration.

  /**
   * Build a Nano4\DB\Update\Table object.
   *
   * You should never have to call this manually, it's called by the
   * Schema class. However, in case you need to override it, the definition
   * is included here.
   *
   * @param Str      $name     The name of the table.
   * @param Object   $parent   The parent Schema object.
   * @param Object   $db       The parent database object.
   * @param Array    $opts     Extra options (reserved for future use.)
   *
   */
  public function __construct ($name, $parent, $db, $opts=[])
  {
    // Set some basics.
    $this->name = $name;
    $this->parent = $parent;
    $this->db = $db;

    // Get the current version of our table schema.
    $this->get_table_schema();

    // Get our table update directory.
    $ourDir = $parent->schemaDir . '/' . $parent->updateDir . '/' . $name;

    if (file_exists($ourDir))
    { // Set our update directory.
      $this->updateDir = $ourDir;

      // Get our table update configuration file.
      $ourConf = $ourDir . '/' . $parent->updateFile;

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
    $versions = $this->get_versions();
    $latest = end($versions);
    reset($versions);
    if (is_numeric($latest))
    {
      $this->latest = $latest;
    }
    elseif (is_array($latest) && isset($latest["version"]))
    {
      $this->latest = $latest["version"];
    }
    else
    {
      throw new \Exception(__CLASS__.": could not determine latest version.");
    }

    // See if we need to be updated.
    $this->check_table_updates();
  }

  // Get the current version of our table schema.
  protected function get_table_schema ()
  {
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
      $this->current = $metadata[$mdVer];
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
    if (version_compare($this->current, $this->latest, '>'))
    {
      throw new \Exception(__CLASS__.": {$this->name} schema is higher than {$this->latest}.");
    }
    elseif (version_compare($this->current, $this->latest, '<'))
    {
      $this->need_update = true;
    }
  }

  public function get_versions ()
  {
    if (isset($this->conf, $this->conf["versions"]))
    {
      return $this->conf["versions"];
    }
    elseif ($this->current !== 0)
    {
      return [$this->current];
    }
    else
    {
      return [$this->parent->defVer];
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
    if (!$this->need_update) return; // If we don't need updating, go away.

    $this->need_update = false;  // Prevent it from trying again.
    $this->update_ran  = true;   // Mark that the update() was called.

    $versions = $this->get_versions();

    foreach ($versions as $version)
    {
      $sql_file = $pre_run = $post_run = $requires = null;
      if (is_numeric($version))
      {
        $ver = $version;
      }
      elseif (is_array($version))
      {
        $ver = $version["version"];
      }
      if ($ver <= $this->current) continue; // Not a newer version.
      if (is_array($version))
      {
        if (isset($version["sql-file"]))
        {
          $sql_file = $this->updateDir . '/' . $version["sql-file"];
        }
        if (isset($version["pre-run"]))
        {
          $pre_run = $version["pre-run"];
        }
        if (isset($version["post-run"]))
        {
          $post_run = $version["post-run"];
        }
        if (isset($version["requires"]))
        {
          $requires = $version["requires"];
        }
      }
      // Handle requirements if they exist.
      if (isset($requires))
      {
        foreach ($requires as $rname => $rver)
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
            if ($rver > $rtable->current)
            {
              $rtable->update();
            }
          }
        }
      }
      if (!isset($sql_file))
      {
        if ($this->current == 0)
        { // The table doesn't exist, create it.
          $sql_file = $this->parent->schemaDir . '/' . $this->name . '.sql';
        }
        else
        { // Use a default upgrade script filename.
          $ver1 = $this->versionString($this->current);
          $ver2 = $this->versionString($ver);
          $sql_file = $this->updateDir . '/' . $ver1 . '-' . $ver2 . '.sql';
        }
      }
      if (!file_exists($sql_file))
      {
        throw new \Exception(__CLASS__.": update file not found: $sql_file");
      }
      if (isset($pre_run) && file_exists($this->updateDir.'/'.$pre_run[0]))
      {
        $class = $this->updateDir.'/'.$pre_run[0];
        require_once "$class";
        $func = "\\UpdateSchema\\".$pre_run[1];
        if (is_callable($func))
        {
          $func($this->db, $this);
        }
      }
      if ($sql_file)
      {
        $this->update_sql_output = $this->db->source($sql_file);
      }
      if (isset($post_run) && file_exists($this->updateDir.'/'.$post_run[0]))
      {
        $class = $this->updateDir.'/'.$post_run[0];
        require_once "$class";
        $func = "\\UpdateSchema\\".$post_run[1];
        if (is_callable($func))
        {
          $func($this->db, $this);
        }
      }
      $this->get_table_schema();
      $this->check_table_updates();
    }
    // TODO: actually catch and report errors.
    $this->update_ok = true;
    return true;
  }

  public function versionString ($num)
  {
    if (!$this->parent->verIsText && fmod(floatval($num), 1) == 0)
      return (string)$num . '.0';
    return (string)$num;
  }

  public function currentVersion ()
  {
    return $this->versionString($this->current);
  }

  public function latestVersion ()
  {
    return $this->versionString($this->latest);
  }

}

