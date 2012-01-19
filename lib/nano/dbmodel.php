<?php

/* A base class for database-driven models.
   It's basically a wrapper around the PDO library.
   It has no ORM stuff, or any other fancy features.
   Feel free to build on it for your own needs.
 */

abstract class DBModel
{
  protected $db;      // Our database.
  
  public function __construct ($opts=array())
  {
    if (!isset($opts['dsn']))
      throw new Exception("Must have a database DSN");
    $this->db = new PDO($opts['dsn'], $opts['user'], $opts['pass']);
  }

  // Create a prepared statement, and set its default fetch style.
  public function query ($statement, $assoc=True)
  {
    $query = $this->db->prepare($statement);
    if ($assoc)
      $query->setFetchMode(PDO::FETCH_ASSOC);
    else
      $query->setFetchMode(PDO::FETCH_NUM);
    return $query;
  }

}

