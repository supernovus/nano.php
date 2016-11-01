<?php
/**
 * Data Container.
 *
 * Represents a "container" which contains objects.
 * The default implementation expects that each member has the same class.
 * Feel free to override this behaviour in your own classes.
 *
 */

namespace Nano\Data;

abstract class Container extends Arrayish
{
  protected $data_itemclass;          // Wrap our items in this class.
  protected $data_index = array();    // Index for hash access.
  protected $data_allow_null = False; // Default option for allowing nulls.

  // Clear our data, including index.
  public function clear ($opts=array())
  {
    $this->data = array();
    $this->data_index = array();
  }

  // Find the position of the child object.
  public function position_of ($item)
  {
    return array_search($item, $this->data, true);
  }

  // Get the item at position x
  public function at_position ($offset)
  {
    if (isset($this->data[$offset]))
      return $this->data[$offset];
  }

  // Add an item to our index. Override this if you need anything
  // more complex than the rules below.
  protected function add_data_index ($item, $indexname=Null)
  {
    // First and foremost, if indexname is specified, it overrides all else.
    if (isset($indexname))
    {
      $this->data_index[$indexname] = $item;
      return True;
    }
    // If we are an object, see if we have the data_identifier() method.
    // Yes, that's right, we are using Duck Typing.
    elseif (is_object($item) && is_callable(array($item, 'data_identifier')))
    {
      $id = $item->data_identifier();
      if (isset($id) && $id !== False)
      {
        $this->data_index[$id] = $item;
        return True;
      }
    }
    // Similarly, if we are an array, and have a key of 'id', use it.
    elseif (is_array($item) && isset($item['id']))
    {
      $id = $item['id'];
      $this->data_index[$id] = $item;
      return True;
    }
    // Anything else, isn't added to our index.
    return False;
  }

  // Get the index number based on key.
  // Only useful if we're using numbered indexing.
  public function get_data_index ($key)
  {
    $dcount = count($this->data);
    for ($i=0; $i < $dcount; $i++)
    {
      $item = $this->data[$i];
      if (is_object($item) && is_callable(array($item, 'data_identifier')))
      {
        $id = $item->data_identifier();
        if ($id == $key) return $i; // We found the index.
      }
      elseif (is_array($item) && isset($item['id']))
      {
        if ($item['id'] == $key) return $i; // Found it.
      }
    }
    return Null; // Sorry, we did not find that key.
  }

  public function get_itemclass ()
  {
    if (isset($this->data_itemclass))
    {
      $class = $this->data_itemclass;
      if (class_exists($class))
      {
        return $class;
      }
    }
    return Null;
  }

  // Overridden load_array() method.
  // If we have a itemclass, all items in the array will be
  // passed to the itemclass's constructor, with 'parent' set to this
  // object. If a validate method exists in the child class,
  // it will be called to ensure the item is valid.
  // Only valid items will be added to our data.
  public function load_array ($array, $opts=array())
  {
    $opts['parent'] = $this;
    $class = $this->get_itemclass();
    foreach ($array as $item)
    {
      if (isset($class))
      { // Wrap our item in a class.
        $item = new $class($item, $opts);
        if (is_callable(array($item, 'validate')))
        {
          if (!$item->validate())
          { // Oops, we didn't pass validation.
            if (is_callable(array($this, 'invalid_data')))
            {
              $this->invalid_data($item);
            }
            continue; // Skip invalid items.
          }
        }
      }
      $this->append($item);
    }
  }

  // Overridden to_array() method.
  public function to_array ($opts=array())
  {
    if (isset($opts['null']))
    {
      $allownull = $opts['null'];
    }
    else
    {
      $allownull = $this->data_allow_null;
    }
    $array = array();
    foreach ($this->data as $val)
    {
      if (is_object($val) && is_callable(array($val, 'to_array')))
      { // Unwrap Data objects.
        $val = $val->to_array($opts);
      }
      if (isset($val) || $allownull)
      { // Add the data.
        $array[] = $val;
      }
    }
    return $array;
  }

  public function append ($item, $indexname=Null)
  {
    $this->data[] = $item;
    $this->add_data_index($item, $indexname);
  }

  public function insert ($item, $pos=0, $indexname=Null)
  {
    parent::insert($item, $pos);
    $this->add_data_index($item, $indexname);
  }

  // We override ArrayAccess to use $this->data_index for its source.
  public function offsetExists ($offset)
  {
    if (!is_string($offset) && !is_int($offset))
    {
      error_log("Invalid offset: ".json_encode($offset));
      return False;
    }
    return array_key_exists($offset, $this->data_index);
  }

  public function offsetGet ($offset)
  {
    if (isset($this->data_index[$offset]))
      return $this->data_index[$offset];
  }

  public function offsetSet ($offset, $value)
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      $this->data[$index] = $value;
    }
    $this->data_index[$offset] = $value;
  }

  public function offsetUnset ($offset)
  {
    $index = $this->get_data_index($offset);
    if (isset($index))
    {
      array_splice($this->data, $index, 1);
    }
    unset($this->data_index[$offset]);
  }

  // And we override is() to use $this->data_index as well.
  public function is ($key)
  {
    return isset($this->data_index[$key]);
  }

  // Find item matching certain rules.
  // The item must either be an array, or implement ArrayAccess.
  public function find ($query, $single=False, $spawn=True)
  {
    if ($single)
    {
      $found = Null;
    }
    elseif ($spawn)
    {
      $found = $this->spawn();
    }
    else
    {
      $found = array();
    }
    // If there is more than 1 query, we need to match all of them.
    $matchAll = count($query) > 1;
    if (!$matchAll)
    { // If we're not using matchAll, extract the query.
      $keys = array_keys($query);
      $key  = $keys[0];
      $val  = $query[$key];
    }
    foreach ($this->data as $item)
    { // Only proceed if we can.
      if (is_array($item) || $item instanceof \ArrayAccess)
      {
        if ($matchAll)
        { // We need to match all queries.
          $matched = True;
          foreach ($query as $key => $val)
          {
            if (!isset($item[$key]) || $item[$key] != $val)
            {
              $matched = False;
              break;
            }
          }
          if ($matched)
          {
            if     ($single)  return $item;
            elseif ($spawn)   $found->append($item);
            else              $found[] = $item;
          }
        } // End if ($matchAll)
        else
        { // We're only matching a single query.
          if ($item[$key] == $val)
          {
            if     ($single)  return $item;
            elseif ($spawn)   $found->append($item);
            else              $found[] = $item;
          }
        }
      } // End test to see if we are a valid array/object.
    } // End data loop.
    return $found;
  } // End of find().

  /**
   * Create a new child item, and return it.
   *
   * @param  mixed  $data   Data to build object from (optional.)
   * @param  array  $opts   Options to build object with (optional.)
   * @return mixed          Either a child Item, or Null on failure.
   */
  public function newItem ($data=Null, $opts=array())
  {
    $opts['parent'] = $this;
    $class = $this->get_itemclass();
    if (isset($class))
    {
      $child = new $class($data, $opts);
      return $child;
    }
  }

  /**
   * Create a new child item, and add it to our data.
   *
   * @param  integer  $pos     Position to insert at. -1 = End, 0 = Beginning.
   *                           Optional, defaults to -1 if not specified.
   * @param  mixed    $data    Optional -- passed to newItem().
   * @param  array    $opts    Optional -- passed to newItem().
   */
  public function addItem ($pos=Null, $data=Null, $opts=array())
  {
    $child = $this->newItem($data, $opts);
    if (isset($child))
    {
      if (isset($pos) && $pos != -1)
      {
        $this->insert($child, $pos);
      }
      else
      {
        $this->append($child);
      }
    }
    return $child;
  }

}


