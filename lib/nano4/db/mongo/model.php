<?php

namespace Nano4\DB\Mongo;

/**
 * MongoDB base class for object models.
 *
 * It's based on the Nano\DB\Model class for SQL databases.
 * As such, it offers a similar interface to that class.
 */
abstract class Model extends Simple implements \Iterator, \ArrayAccess
{
  use \Nano4\Meta\ClassID;

  public $parent;

  protected $childclass;
  protected $resultclass;

  // By default we save the build opts in the Model class.
  // Override in subclasses, or by using a model config: "saveOpts":false
  protected $save_build_opts = true;

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

    parent::__construct($opts);
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
      $opts['parent'] = $this;
      $opts['data']   = $data;
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
      $fopts = isset($opts['findopts']) ? $opts['findopts'] : [];
      $results = $this->data->find($opts['find'], $fopts);
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

}
