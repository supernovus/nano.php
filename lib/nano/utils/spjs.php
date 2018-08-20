<?php

namespace Nano\Utils;

/**
 * Statistical Processing for JSON Structures
 *
 * This is an extremely simplistic 
 */
class SPJS
{
  /**
   * The underlying data set. An array of associative arrays.
   * Could also use Data objects, as long as it supports the array accessor
   * format, e.g. $object['key'];
   */
  protected $dataset;

  /**
   * Build a new SPJS instance.
   */
  public function __construct ($dataset, $opts=[])
  {
    $this->dataset = $dataset;
  }

  /**
   * Return a copy of the data.
   */
  public function getData ()
  {
    return $this->dataset;
  }

  /**
   * Process a set of statements against our entire dataset.
   */
  public function process ($stmts, $opts=[])
  {
    foreach ($this->dataset as &$datarec)
    {
      $this->process_statements($datarec, $stmts, $opts);
    }
    return $this->dataset;
  }

  /**
   * Process a set of statements against a single data record.
   */
  public function process_statements (&$datarec, $stmts, $opts=[])
  {
    // Single defaults to false on the top-level set of statements.
    $single = isset($opts['single']) ? $opts['single'] : false;
    $globalretval = false;
    foreach ($stmts as $stmt)
    {
      $retval = false;
      if (is_array($stmt))
      { // Most statements will be arrays of one sort or another.
        if (isset($stmt[0]))
        { // Flat array, this is a nested set of statements.
          $opts['single'] = true; // Default single to true on nested groups.
          $retval = $this->process_statements($datarec, $stmt, $opts);
        }
        else
        { // Check for command statements.
          if (isset($stmt['always']))
          { // An 'always' statement always uses the return value specified.
            // This can be used to provide a default value if no tests match,
            // or to disable a test without removing it entirely.
            $retval = $stmt['always'];
          }
          elseif (isset($stmt['if']))
          { // An 'if' statement. This can redefine the return value.
            $retval = $this->process_if($datarec, $stmt['if'], $opts);
          }

          // The following commands are only executed if the $retval is true.
          if ($retval && isset($stmt['set']))
          { // A 'set' statement, defines or refines properties.
            $this->process_set($datarec, $stmt['set'], $opts);
          }
          if ($retval && isset($stmt['unset']))
          { // An 'unset' statement, removes properties.
            $this->process_unset($datarec, $stmt['unset'], $opts);
          }
        }
      }
      elseif (is_bool($stmt))
      { // A shortcut for redefining 'single' for this group.
        $single = $stmt;
      }

      if ($single && $retval)
      { // Only a single statement in this group can be true, return now.
        return $retval;
      }
      elseif ($retval)
      { // At least one statement was true, update the global return value.
        $globalretval = $retval;
      }
    }
    return $globalretval;
  }

  /**
   * Internal method to process 'if' statements.
   *
   * As soon as any test fails, this returns false.
   *
   * Tests (in JSON syntax):
   *
   *  {
   *    "key1": null,    // Only true if the value is null or not set.
   *    "key2": 1,       // A scalar is true if the value matches.
   *    "key3": [1,2]    // True if the value is ONE of the items in the array.
   *    "key4":          // Special tests, must match ALL defined keys:
   *    {
   *      "<":  5        // True if the value is less than this.
   *      ">":  1        // True if the value is greater than this.
   *      "!=": 2        // True if the value is not equal to this.
   *    }
   *  }
   */
  protected function process_if (&$datarec, $conds, $opts)
  {
    foreach ($conds as $key => $want)
    {
      // The data value we're testing against. Will be null is not set.
      $dataval = isset($datarec[$key]) ? $datarec[$key] : null;

      if (is_null($want))
      { // Only true if the value is null.
        if (!is_null($dataval))
        {
          return false;
        }
      }
      elseif (is_null($dataval))
      { // Any other null dataval is an automatic failure.
        return false;
      }
      elseif (is_scalar($want))
      { // We want a single value.
        if ($dataval != $want)
        {
          return false;
        }
      }
      elseif (is_array($want))
      { 
        if (isset($want[0]))
        { // A flat array, the value must match one of these.
          if (!in_array($dataval, $want))
          {
            return false;
          }
        }
        else
        { // Special keys. We need to match all that are defined.
          if (isset($want['<']))
          { // Less than.
            if ($dataval >= $want['<'])
            {
              return false;
            }
          }
          if (isset($want['>']))
          { // Greater than.
            if ($dataval <= $want['>'])
            {
              return false;
            }
          }
          if (isset($want['!=']))
          { // Not equal to.

            if ($dataval == $want['!='])
            {
              return false;
            }
          }
        }
      }
    }
    // If we reached here, all tests passed.
    return true;
  }

  protected function process_set (&$datarec, $props, $opts)
  {
    foreach ($props as $key => $val)
    {
      $datarec[$key] = $val;
    }
  }

  protected function process_unset (&$datarec, $props, $opts)
  {
    foreach ($props as $key)
    {
      unset($datarec[$key]);
    }
  }

}