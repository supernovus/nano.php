<?php

namespace Nano4\Controllers;

/**
 * Adds 'need' and 'needs' methods. This is no longer included by default
 * in the Advanced controller template. If you want it, include it yourself.
 * It expects that at least the Messages trait is included.
 */

trait Needs
{
  protected $shown = array();   // If an id exists in shown, don't show again.

  /**
   * Check for a required option in an array.
   * The option must exist, and it must not be empty or just whitespace.
   * 
   * Useful for GET, POST and REQUEST options, and other data.
   * It can handle missing options in various ways.
   */
  public function need ($what, $array, $opts=array())
  {
    // We support different return types.
    $return_value  = 0;
    $return_bool   = 1;
    $return_status = 2;

    // If we're using the 'status' return type, these are the statuses.
    $status_good    = 1;  //  1 = Item was found, and is valid.
    $status_missing = 0;  //  0 = Item was not found.
    $status_invalid = -1; // -1 = Item was found, but is not valid.

    if (isset($opts['getstatus']) && $opts['getstatus'])
    {
      $return = $return_status;
    }
    elseif (isset($opts['bool']) && $opts['bool'])
    {
      $return = $return_bool;
    }
    else
    {
      $return = $return_value;
    }

    $found = False;
    $valid = False;

    if (isset($array[$what]) && trim($array[$what]) != '')
    {  // We found the item.
      $found = True;
      if (isset($opts['valid_values']))
      { // Look for the value in an array of values.
        if (is_numeric(array_search($array[$what], $opts['valid_values'])))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_keys']))
      { // See if the value is a key in an array.
        if (array_key_exists($array[$what], $opts['valid_keys']))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_match']))
      { // See if the value matches a regular expression.
        if (preg_match($opts['valid_match'], $array[$what]))
        {
          $valid = True;
        }
      }
      elseif (isset($opts['valid_check']))
      { // Use a callback function to validate the value.
        // The value to be checked will be sent as the first parameter,
        // and the name of the field ($what) will be sent as the second.
        // It must return a boolean value representing the validity of the
        // value in question.
        $callback = $opts['valid_check'];
        if (is_callable($callback))
        {
          $valid = call_user_func($callback, $array[$what], $what);
        }
      }
      else
      { // No validation rules exist, therefore the value is valid.
        $valid = True;
      }
    }

    // Messages and such default to errors.
    $show_msg = True;
    $msg_type = 'error';
    $showid   = Null;

    if (isset($opts['show_msg']))
    {
      $show_msg = $opts['show_msg'];
    }

    if (isset($opts['msg_type']))
    {
      $msg_type = $opts['msg_type'];
    }

    if ($found && $valid)
    {
      if ($return == $return_bool)
        return True;
      elseif ($return == $return_status)
      {
        return $status_good;
      }
      else
        return $array[$what];
    }
    elseif (!$found)
    {
      $status   = $status_missing;
      $msg_name = 'needed_field';
      if (isset($opts['need_msg']))
      {
        $msg_name = $opts['need_msg'];
      }
      if (isset($opts['need_type']))
      {
        $msg_type = $opts['need_type'];
      }
      if (isset($opts['show_need']))
      {
        $show_msg = $opts['show_need'];
      }
    }
    elseif (!$valid)
    {
      $status   = $status_invalid;
      $msg_name = 'invalid_value';
      if (isset($opts['invalid_msg']))
      {
        $msg_name = $opts['invalid_msg'];
      }
      if (isset($opts['invalid_type']))
      {
        $msg_type = $opts['invalid_type'];
      }
      if (isset($opts['show_invalid']))
      {
        $show_msg = $opts['show_invalid'];
      }
    }

    if (isset($opts['show_id']))
    { // If show_id is set, only one message with the given id will
      // be displayed. This prevents duplicate messages from building up.
      $showid = $opts['show_id'];
      if (isset($this->shown[$showid]) && $this->shown[$showid])
      {
        $show_msg = False;
      }
    }

    if ($show_msg)
    {
      $fieldname = $this->text[$what];
      $msg_opts  = array('type'=>$msg_type, 'reps'=>array($fieldname));
      $this->message($msg_name, $msg_opts);
      if (isset($showid))
      {
        $this->shown[$showid] = True;
      }
    }

    // If we reached here, the field was invalid or not found.
    if ($return == $return_bool)
    {
      return False;
    }
    elseif ($return == $return_status)
    {
      return $status;
    }
    else
    {
      return Null;
    }
  }

  /**
   * Take an array of values to search for, and an array to search
   * for them in, and do the rest automagically.
   *
   * Depending on the categorize option, the output will be one of
   * two forms. If categorize is False or not specified, then we will
   * return an associative array where the key is the name of the
   * member, and the value is one of:
   *
   *   1 = Data was found, and is valid.
   *   0 = Data was not found.
   *  -1 = Data was found, but is not valid.
   *
   * If 'categorize' is specified and is True, then we will return
   * an assocative array with three associative arrays inside it:
   *
   *  'correct'   Will contain keys for valid fields, and the valid value.
   *  'invalid'   Will contain keys for invalid fields, and the invalid value.
   *  'missing'   Will contain keys for missing fields, and a value of True.
   *
   */
  public function needs ($rules, $array, $defopts=array())
  {
    $results = array();
    if (isset($defopts['categorize']) && $defopts['categorize'])
    {
      $categorize = True;
      $results['missing'] = array();
      $results['invalid'] = array();
      $results['correct'] = array();
      unset($defopts['categorize']);
    }
    foreach ($rules as $key => $val)
    {
      if (is_numeric($key))
      { // Use the default options.
        $want = $val;
        $opts = $defopts;
      }
      else
      { // We have specified some form of options.
        $want = $key;
        if (is_array($val))
        {
          $opts = $val;
        }
        elseif (is_bool($val))
        {
          if ($val)
          { // Use the default options.
            $opts = $defopts;
          }
          else
          { // Use the default options, but show no messages.
            $opts = $defopts;
            $opts['show_msg'] = False;
          }
        }
        else
        { // Sorry we didn't recognize your options.
          $opts = $defopts;
        }
      }
      $opts['getstatus'] = True;
      $status = $this->need($want, $array, $opts);
      if ($categorize)
      {
        if ($status == 1)
        {
          $results['correct'][$want] = $array[$want];
        }
        elseif ($status == -1)
        {
          $results['invalid'][$want] = $array[$want];
        }
        elseif ($status == 0)
        {
          $results['missing'][$want] = True;
        }
      }
      else
      { // Return the status code.
        $results[$want] = $status;
      }
    }
    return $results;
  }

}

