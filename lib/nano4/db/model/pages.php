<?php

namespace Nano4\DB\Model;

/**
 * The old DB\Model pager.
 */
trait Pages
{
  public $sort_orders = array();
  public $sort_items  = array();
  public $default_sort_order;
  public $default_page_count = 10;

  /**
   * Call from your custom constructor
   */
  protected function init_sort_order ()
  {
    // Default sort orders if you don't override it.
    if (count($this->sort_orders) == 0)
    {
      if (count($this->sort_items) == 0)
      {
        $this->sort_items = array($pk);
      }
      $this->build_sort_orders();
    }

    // If no default sort order has been specified, we do it now.
    if (!isset($this->default_sort_order))
    {
      $sort_orders = array_keys($this->sort_orders);
      $this->default_sort_order = $sort_orders[0];
    }
  }

  // Build a default set of sort_orders based on sort_items.
  protected function build_sort_orders ()
  {
    foreach ($this->sort_items as $sortref => $sortrow)
    {
      if (is_numeric($sortref)) 
      {
        $sortref = $sortrow;
      }

      $this->sort_orders[$sortref.'_up']   = "$sortrow ASC";
      $this->sort_orders[$sortref.'_down'] = "$sortrow DESC";
    }
  }

  /**
   * Get a page of results.
   */
  public function listPage ($where, $pageopts, $cols=Null, $data=[])
  {
    $pager = $this->pager($pageopts);
    $whereopts = ['where'=>$where];
    if (isset($cols))
      $whereopts['cols'] = $cols;
    if (isset($data))
      $whereopts['data'] = $data;
    return $this->select($whereopts);
  }

  /**
   * Page count.
   */
  public function pagecount ($rowcount=Null, $opts=array())
  {
    if (!is_numeric($rowcount))
    {
      if (isset($opts['data']))
        $data = $opts['data'];
      else
        $data = [];
      $rowcount = $this->rowcount($rowcount, $data);
    }

    if (isset($opts['count']) && $opts['count'] > 0)
      $perpage = $opts['count'];
    else
      $perpage = $this->default_page_count;

    $pages = ceil($rowcount / $perpage);

    return $pages;
  }

  /**
   * Generate ORDER BY and LIMIT statements, based on a provided sort order,
   * number of items to display per page, and what page you are currently on.
   * NOTE: pages start with 1, not 0.
   */
  public function pager ($opts=array())
  {
    if (isset($opts['sort']))
      $sort = $opts['sort'];
    else
      $sort = $this->default_sort_order;

    if (isset($opts['page']) && $opts['page'] > 0)
      $page = $opts['page'];
    else
      $page = 1;

    if (isset($opts['count']) && $opts['count'] > 0)
      $count = $opts['count'];
    else
      $count = $this->default_page_count;

    $offset = $count * ($page - 1);

    if (isset($this->sort_orders[$sort]))
    {
      $statement = "ORDER BY {$this->sort_orders[$sort]} LIMIT $offset, $count";
    }
    else
    {
      $statement = "LIMIT $offset, $count";
    }

#    error_log("pager statement: $statement");
    return $statement;
  }


}
