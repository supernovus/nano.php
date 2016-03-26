<?php

namespace Nano4\DB\Mongo;
use \MongoDB\BSON\ObjectID;

/**
 * MongoDB base class for object models.
 *
 * It's based on the Nano\DB\Model class for SQL databases.
 * As such, it offers a similar interface to that class.
 */
abstract class Model extends Simple implements \Iterator, \ArrayAccess
{
  use \Nano4\DB\ModelCommon, \Nano4\Meta\ClassID;

  public $parent;

  protected $childclass;
  protected $resultclass;

  protected $primary_key = '_id';

  // By default we save the build opts in the Model class.
  // Override in subclasses, or by using a model config: "saveOpts":false
  protected $save_build_opts = true;

  protected $resultset;

  protected $serialize_ignore = ['server','db','data','resultset'];

  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (isset($opts['__classid']))
    {
      $this->__classid = $opts['__classid'];
    }
    if (isset($opts['childclass']))
    {
      $this->childclass = $opts['childclass'];
    }
    if (isset($opts['resultclass']))
    {
      $this->resultclass = $opts['resultclass'];
    }
    if (isset($opts['primary_key']))
    {
      $this->primary_key = $opts['primary_key'];
    }

    parent::__construct($opts);
  }

  public function __sleep ()
  {
    $properties = get_object_vars($this);
    foreach ($this->serialize_ignore as $ignored)
    {
      unset($properties[$ignored]);
    }
    return array_keys($properties);
  }

  public function __wakeup ()
  {
    $this->get_collection();
  }

  public function wrapRow ($data, $opts=[])
  {
    if (isset($opts['rawDocument']) && $opts['rawDocument'])
      return $data;
    if ($data)
    {
      $object = $this->newChild($data, $opts);
      if (isset($object))
        return $object;
      else
        return $data;
    }
  }

  public function newChild ($data=[], $opts=[])
  {
    if (isset($opts['childclass']))
      $class = $opts['childclass'];
    else
      $class = $this->childclass;
    if ($class)
    {
      $data = $this->populate_known_fields($data);
      $opts['parent'] = $this;
      $opts['data']   = $data;
      $opts['pk']     = $this->primary_key;
      return new $class($opts);
    }
  }

  public function getResults ($opts=[])
  {
    if (isset($opts['resultclass']))
      $class = $opts['resultclass'];
    else
      $class = $this->resultclass;
    if ($class && (!isset($opts['rawResults']) || !$opts['rawResults']))
    {
      $opts['parent'] = $this;
      return new $class($opts);
    }

    if (isset($opts['find']))
    {
      $data = $this->get_collection();
      $fopts = isset($opts['findopts']) ? $opts['findopts'] : [];
      $results = $data->find($opts['find'], $fopts);
      if (isset($opts['childclass']) || isset($this->childclass))
      {
        $wrapped = [];
        foreach ($results as $result)
        {
          $wrap = $this->wrapRow($result, $opts);
          if (isset($wrap))
            $wrapper[] = $wrap;
        }
        return $wrapped;
      }
      return $results;
    }
  }

  public function find ($find=[], $findopts=[], $classopts=[])
  {
    $classopts['find'] = $find;
    $classopts['findopts'] = $findopts;
    return $this->getResults($classopts);
  }

  public function findOne ($find=[], $findopts=[], $classopts=[])
  {
    $data = $this->get_collection();
    $result = $data->findOne($find, $findopts);
    return $this->wrapRow($result, $classopts);
  }

  public function getDocById ($id, $findopts=[], $classopts=[])
  {
    if (is_string($id))
    {
      $id = new ObjectID($id);
    }
    if (!($id instanceof ObjectID))
    {
      throw new \Exception("invalid id passed to getDocById");
    }
    $pk = $this->primary_key;
    return $this->findOne([$pk => $id], $findopts, $classopts);
  }

  public function save ($doc, $update=null)
  {
    $pk = $this->primary_key;
    $data = $this->get_collection();
    if (isset($update) && (is_string($doc) || $doc instanceof ObjectID))
    {
      $doc = [$pk=>$doc];
    }
    if (isset($doc[$pk]))
    {
      if (is_string($doc[$pk]))
      {
        $doc[$pk] = new ObjectID($doc[$pk]);
      }
      elseif (is_array($doc[$pk]) && isset($doc[$pk]['$id']))
      {
        $doc[$pk] = new ObjectID($doc[$pk]['$id']);
      }
      $find = [$pk => $doc[$pk]];
      if (isset($update))
      {
        $res = $data->updateOne($find, $update);
      }
      else
      {
        $res = $data->replaceOne($find, $doc);
      }
      $isnew = 0;
    }
    else
    {
      $res = $data->insertOne($doc);
      $isnew = 1;
    }
    return [$isnew, $res, $doc];
  }

  public function deleteId ($id)
  {
    $pk = $this->primary_key;
    if (is_string($id))
    {
      $id = new ObjectId($id);
    }
    elseif (is_array($id) && isset($id['$id']))
    {
      $id = new ObjectID($id['$id']);
    }
    $data = $this->get_collection();
    return $data->deleteOne([$pk => $id]);
  }

  // Iterator interface

  public function rewind ()
  {
    $this->resultset = $this->find();
    return $this->resultset->rewind();
  }

  public function current ()
  {
    $this->resultset->current();
  }

  public function next ()
  {
    $this->resultset->next();
  }

  public function key ()
  {
    $this->resultset->key();
  }

  public function valid ()
  {
    $this->resultset->valid();
  }

  // ArrayAccess interface.

  public function offsetGet ($offset)
  {
    return $this->getDocById($offset);
  }

  public function offsetExists ($offset)
  {
    $doc = $this->getDocById($offset);
    if ($doc)
      return true;
    return false;
  }

  public function offsetSet ($offset, $doc)
  {
    $pk = $this->primary_key;
    $id = new ObjectID($offset);
    $doc->$pk = $id;
    if (is_callable([$doc, 'save']))
      $doc->save();
    else
      $this->save($doc);
  }

  public function offsetUnset ($offset)
  {
    return $this->deleteId($offset);
  }
}

