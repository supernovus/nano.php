<?php

namespace Nano4\DB\Mongo;

/**
 * MongoDB Simple connection library.
 */
class Simple
{
  protected $server;
  protected $db;
  protected $data;

  protected $mongo_server_key    = 'mongo.server';
  protected $mongo_db_key        = 'mongo.database';
  protected $mongo_cache_dbs     = 'mongo.cache.dbs';
  protected $mongo_cache_servers = 'mongo.cache.servers';

  // If you don't want to auto connect to the collection, set this to false.
  protected $auto_connect = true;

  // If you aren't using auto_connect, or need to reconnect, set this to true.
  protected $save_build_opts = false;
  protected $build_opts;

  public function __construct ($opts=[])
  {
    if (isset($opts['saveOpts']))
    {
      $this->save_build_opts = $opts['saveOpts'];
    }

    if ($this->save_build_opts)
    {
      $this->build_opts = $opts;
    }

    if (isset($opts['autoConnect']))
    {
      $this->auto_connect = $opts['autoConnect'];
    }

    if ($this->auto_connect)
    {
      $collection = $this->get_collection($opts);
      if (!isset($collection))
      {
        throw new \Exception("invalid collection, could not build.");
      }
    }
  }

  public function get_server ($opts=[])
  {
    $nano = \Nano4\get_instance();
    $msk  = $this->mongo_server_key;
    $msc  = $this->mongo_cache_servers;

    if (isset($this->server))
    {
      return $this->server;
    }

    if (isset($this->build_opts) && count($opts) == 0)
    {
      $opts = $this->build_opts;
    }

    if (isset($opts['server']))
    {
      $server = $opts['server'];
    }
    elseif (isset($opts['dsn']))
    {
      $server = $opts['dsn'];
    }
    elseif (isset($nano[$msk]))
    {
      $server = $nano[$msk];
    }
    else
    {
      $server = 'mongodb://localhost:27017';
    }

    if (isset($nano[$msc], $nano[$msc][$server]))
    {
      return $this->server = $nano[$msc][$server];
    }

    $this->server = new \MongoDB\Client($server);
    if (isset($nano[$msc]))
    {
      $nano[$msc][$server] = $this->server;
    }
    else
    {
      $nano[$msc] = [$server=>$this->server];
    }
    return $this->server;
  }

  public function get_db ($opts=[])
  {
    $nano = \Nano4\get_instance();
    $mdk  = $this->mongo_db_key;
    $mdc  = $this->mongo_cache_dbs;

    if (isset($this->db))
    {
      return $this->db;
    }

    if (isset($this->build_opts) && count($opts) == 0)
    {
      $opts = $this->build_opts;
    }

    if (isset($opts['database']))
    {
      $db = $opts['database'];
    }
    elseif (property_exists($this, 'database') && isset($this->database))
    {
      $db = $this->database;
    }
    elseif (isset($nano[$mdk]))
    {
      $db = $nano[$mdk];
    }
    else
    {
      throw new \Exception("No database name could be found.");
    }

    if (isset($nano[$mdc], $nano[$mdc][$db]))
    {
      return $this->db = $nano[$mdc][$db];
    }

    $server = $this->get_server($opts);

    $this->db = $server->selectDatabase($db);
    if (isset($nano[$mdc]))
    {
      $nano[$mdc][$db] = $this->db;
    }
    else
    {
      $nano[$mdc] = [$db=>$this->db];
    }
    return $this->db;
  }

  public function get_collection ($opts=[])
  {
    if (isset($this->data))
    {
      return $this->data;
    }

    if (isset($this->build_opts) && count($opts) == 0)
    {
      $opts = $this->build_opts;
    }

    if (isset($opts['collection']))
    {
      $collection = $opts['collection'];
    }
    elseif (isset($opts['table']))
    {
      $collection = $opts['table'];
    }
    elseif (property_exists($this, 'collection') && isset($this->collection))
    {
      $collection = $this->collection;
    }
    elseif (property_exists($this, 'table') && isset($this->table))
    {
      $collection = $this->table;
    }
    else
    {
      throw new \Exception("No collection name could be found.");
    }

    $db = $this->get_db($opts);

    return $this->data = $db->selectCollection($collection);
  }
}

