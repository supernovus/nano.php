<?php

namespace Nano\Models\PDO;

/**
 * A basic access log.
 *
 * Records a bunch of data to a database for auditing purposes.
 */

abstract class AccessLog extends \Nano\DB\PDO\Model
{
  use \Nano\Models\Common\AccessLog;

  protected $childclass  = '\Nano\Models\PDO\AccessRecord';
  protected $resultclass = '\Nano\DB\PDO\ResultSet';

  public $known_fields =
  [
    'success' => false, 'message' => null, 'context' => null, 
    'headers' => null, 'userdata' => null, 'timestamp' => 0,
  ];
}

class AccessRecord extends \Nano\DB\PDO\Item implements \Nano\Models\Common\AccessRecord
{
  protected function get_field ($field)
  {
    return isset($this->data[$field]) 
      ? json_decode($this->data[$field], true)
      : null;
  }

  protected function set_field ($field, $data)
  {
    $this->data[$field] = json_encode($data);
  }

  public function _get_headers ()
  {
    return $this->get_field('headers');
  }

  public function _set_headers ($headers)
  {
    $this->set_field('headers', $headers);
  }

  public function _get_context ()
  {
    return $this->get_field('context');
  }

  public function _set_context ($context)
  {
    $this->set_field('context', $context);
  }

  public function _get_userdata ()
  {
    return $this->get_field('userdata');
  }

  public function _set_userdata ($user)
  {
    $this->set_field('userdata', $user);
  }

}

