<?php

namespace Nano\DB\PDO\Simple;

trait NativeDB
{
  /**
   * Run a .sql file within the current database.
   *
   * Requires access to the mysql, pgsql, or sqlite3 binary.
   * 
   * It also requires the 'keep_config' parameter was set as true.
   *
   * @param Str $filename  The SQL file to run.
   */
  public function source ($filename)
  {
    if (!isset($this->db_conf))
    {
      throw new \Exception(__CLASS__.": source() requires 'keep_config'");
    }
    $dsn = $this->db_conf['dsn'];

    $hostinfo = $this->get_host_info();
    $host = $hostinfo[0];
    $port = $hostinfo[1];

    if (strpos($dsn, 'mysql') !== False)
    {
      $command = "mysql -u{$this->db_conf['user']} -p{$this->db_conf['pass']}";
      if (is_string($host))
        $command .= " -h $host";
      if (is_numeric($port))
        $command .= " -P $port";
      $command .= " -D {$this->name} < $filename"; 
    }
    elseif (strpos($dsn, 'pgsql'))
    {
      putenv('PGPASSWORD='.$this->db_conf['pass']);
      $command = "psql -f $filename -U {$this->db_conf['user']}";
      if (is_string($host))
        $command .= " -h $host";
      if (is_numeric($port))
        $command .= " -p $port";
      $command .= " -d " . $this->name;
    }
    elseif (strpos($dsn, 'sqlite'))
    {
      $command = "sqlite3 -init $filename {$this->name} '.q'";
    }
    else
    {
      throw new \Exception(__CLASS__.": unsupported database type: $dsn");
    }
    return shell_exec($command); 
  }

  /**
   * Get host and port information.
   * Requires the 'keep_config' parameter was set as true.
   */
  public function get_host_info ()
  {
    if (!isset($this->db_conf))
    {
      throw new \Exception(__CLASS__.": get_host_info() requires 'keep_config'");
    }
    $dsn = $this->db_conf['dsn'];

    $matches = [];

    $host = false;
    $port = false;

    if (preg_match('/host=(\w+)/', $dsn, $matches))
    {
      $host = $matches[1];
    }
    if (preg_match('/port=(\d+)/', $dsn, $matches))
    {
      $port = $matches[1];
    }
    return [$host, $port];
  }
} // trait NativeDB

