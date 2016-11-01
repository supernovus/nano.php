<?php

namespace Nano\DB;

trait ModelCommon
{
  /**
   * Override this in your model classes.
   * It's a list of fields we know about.
   * DO NOT set the primary key in here.
   */
  public $known_fields;
                      
  /**
   * Check and see if a field is known.
   */
  public function is_known ($field)
  {
    // First check to see if it is the primary key.
    if ($field == $this->primary_key)
    {
      return True;
    }

    if (isset($this->known_fields) && is_array($this->known_fields))
    {
      // Next look through our known_fields.
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        {
          $name = $val;
        }
        else
        {
          $name = $key;
        }
        if ($field == $name)
        {
          return True;
        }
      }
    }

    return False;
  }

  protected function populate_known_fields ($data)
  {
    if (isset($this->known_fields) && is_array($this->known_fields))
    {
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        {
          $field   = $val;
          $default = $this->default_value;
        }
        else
        {
          $field   = $key;
          $default = $val;
        }
        if (!array_key_exists($field, $data))
        { // Add a placeholder value, to ensure the field is present.
          $data[$field] = $default;
        }
      }
    }
    return $data;
  }

}

