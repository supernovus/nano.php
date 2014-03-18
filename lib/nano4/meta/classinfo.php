<?php

namespace Nano4\Meta;

Trait ClassInfo
{
  // Return the lowercase "basename" of our class.
  // In classes supporting the class_id() method, we use it.
  public function get_classname ($object=Null)
  {
    if (is_null($object))
      $object = $this;

    if (is_callable([$object, 'class_id']))
    {
      return $object->class_id();
    }

    $classpath = explode('\\', get_class($object));
    $classname = strtolower(end($classpath));
    return $classname;
  }

  // Return the lowercase "dirname" of our class.
  public function get_namespace ($object=Null)
  {
    if (is_null($object))
      $object = $this;
    $classpath = explode('\\', get_class($object));
    array_pop($classpath); // Eliminate the "basename".
    $namespace = join('\\', $classpath);
    return $namespace;
  }

  /**
   * Get either our parent class, or a parent class in our heirarchy with
   * a given classname (as returned by get_classname(), so no Namespaces.)
   */
  public function get_parent ($class=Null)
  {
    $parent = $this->parent;
    if (is_null($class))
    {
      return $parent;
    }
    else
    { // The get_classname() function uses lowercase names.
      $class = strtolower($class);
    }

    if (isset($parent))
    {
      if (is_callable(array($parent, 'get_classname')))
      {
        $parentclass = $parent->get_classname();
      }
      else
      {
        $parentclass = $this->get_classname($parent);
      }

      if ($parentclass == $class)
      {
        return $parent;
      }
      elseif (is_callable(array($parent, 'get_parent')))
      {
        return $parent->get_parent($class);
      }
    }
  }
} // end Trait ClassInfo

