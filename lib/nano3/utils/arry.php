<?php

namespace Nano3\Utils;

/**
 * Some useful utility functions that work on Arrays.
 *
 * The easiest way to use this is:
 *   use Nano3\Utils\Arry;
 * Then just call the functions like:
 *   Arry::swap($array, 2, 4);
 *
 * You can make an object instance, but it's not needed.
 *
 */
class Arry
{
  /**
   * Swap the positions of elements in an array.
   *
   * This operates directly on the array, and does not return anything.
   *
   * @param array   $array       The array to operate on.
   * @param mixed   $pos1        First position or key to swap.
   * @param mixed   $pos2        Second position or key to swap.
   */
  public function swap (&$array, $pos1, $pos2)
  {
    $temp = $array[$pos2];
    $array[$pos2] = $array[$pos1];
    $array[$pos1] = $temp;
  }

  /**
   * Rename an array key.
   *
   * This operates directly on the array. 
   * It returns true on success, and false on failure.
   *
   * @param array   $array      The array to operate on.
   * @param string  $curname    Current key name.
   * @param string  $newname    New key name.
   * @param bool    $overwrite  Optional, overwrite existing keys.
   */
  public function rename_key (&$array, $curname, $newname, $overwrite=False)
  {
    if (array_key_exists($oldname, $array))
    {
      if ($overwrite || !isset($array[$newname]))
      {
        $array[$newname] = $array[$curname];
        unset($array[$curname]);
        return True;
      }
    }
    return False;
  }

  /**
   * Generate a Cartesian product from a set of arrays.
   *
   * Taken from http://www.theserverpages.com/php/manual/en/ref.array.php
   * Reformatted to fit with Nano.
   *
   * @param array  $arrays   An array of arrays to get the product of.
   * @return array           An array of arrays representing the product.
   */
  public function cartesian_product($arrays) 
  {
    //returned array...
    $cartesic = array();
   
    //calculate expected size of cartesian array...
    $size = (sizeof($arrays)>0) ? 1 : 0;
    foreach ($arrays as $array)
    {
      $size = $size * sizeof($array);
    }
    for ($i=0; $i<$size; $i++) 
    {
      $cartesic[$i] = array();
       
      for ($j=0; $j<sizeof($arrays); $j++)
      {
        $current = current($arrays[$j]); 
        array_push($cartesic[$i], $current);    
      }
      // Set cursor on next element in the arrays, beginning with the last array
      for ($j=(sizeof($arrays)-1); $j>=0; $j--)
      {
        //if next returns true, then break
        if (next($arrays[$j])) 
        {
          break;
        } 
        else 
        { // If next returns false, then reset and go on with previous array.
          reset($arrays[$j]);
        }
      }
    }
    return $cartesic;
  }

  /**
   * Generate a set of subsets of a fixed size.
   *
   * Based on example from:
   * stackoverflow.com/questions/7327318/power-set-elements-of-a-certain-length
   *
   * @param array   $array      Array to find subsets of.
   * @param int     $size       Size of subsets we want.
   */
  public function subsets ($array, $size)
  {
    if (count($array) < $size) return array();
    if (count($array) == $size) return array($array);

    $x = array_pop($array);
    if (is_null($x)) return array();

    return array_merge
    ( 
      self::subsets($array, $size), 
      self::merge_into_each($x, self::subsets($array, $size-1))
    );
  }

  /**
   * Merge an item into a set of arrays.
   *
   * A part of subsets(), taken from same example.
   *
   * @param mixed  $x       Item to merge into arrays.
   * @param array  $arrays  Array of arrays to merge $x into.
   * @return array          A copy of original array, with merged data.
   */
  public function merge_into_each ($x, $arrays)
  {
    foreach ($arrays as &$array) array_push($array, $x);
    return $arrays;
  }

  /**
   * Generate a powerset.
   *
   * Generate an array of arrays representing the powerset of elements
   * from the original array.
   *
   * Based on code from:
   * http://bohuco.net/blog/2008/11/php-arrays-power-set-and-all-permutations/
   *
   * @param array  $array   The input array to generate powerset from.
   * @return array          An array of arrays representing the powerset.
   */
  public function powerset ($array)
  {
    $results = array(array());
    foreach ($array as $j => $element)
    {
      $num = count($results);
      for ($i=0; $i<$num; $i++)
      {
        array_push($results, array_merge(array($element), $results[$i]));
      }
    }
    return $results;
  }

  /**
   * Another powerset algorithm.
   * Found in a few places on the net.
   */
  public function powerset2 ($in, $minLength=1)
  {
    $count = count($in);
    $members = pow(2,$count);
    $return = array();
    for ($i = 0; $i < $members; $i++)
    {
      $b = sprintf("%0".$count."b",$i);
      $out = array();
      for ($j = 0; $j < $count; $j++)
      {
        if ($b{$j} == '1') $out[] = $in[$j];
      }
      if (count($out) >= $minLength)
      {
        $return[] = $out;
      }
    }
    return $return; 
  }

  // TODO: add permutations and other useful helpers.

}
