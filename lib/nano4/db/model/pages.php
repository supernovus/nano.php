<?php

namespace Nano4\DB\Model;

/**
 * The old DB\Model pager.
 */
trait Pages
{
  abstract public function get_sort_orders ();
  abstract public function get_sort_items ();
  abstract public function get_default_sort_order ();
  abstract public function get_default_per_page ();

  abstract protected function set_sort_orders ($value);
  abstract protected function set_sort_items ($value);
  abstract protected function set_default_sort_order ($value);
  abstract protected function set_default_per_page ($value);

  /**
   * Call from your custom constructor
   */
  protected function init_sort_order ()
  {
    // Default sort orders if you don't override it.
    if (count($this->get_sort_orders()) == 0)
    {
      if (count($this->get_sort_items()) == 0)
      { // The default sort_items is simply the primary key.
        $this->set_default_sort_items([$this->primary_key]);
      }
      $this->build_sort_orders();
    }

    // If no default sort order has been specified, we do it now.
    if (!$this->get_default_sort_order())
    {
      $sort_orders = array_keys($this->get_sort_orders());
      $this->set_default_sort_order($sort_orders[0]);
    }
  }

  // Build a default set of sort_orders based on sort_items.
  protected function build_sort_orders ()
  {
    $sorders = [];
    foreach ($this->get_sort_items() as $sortref => $sortrow)
    {
      if (is_numeric($sortref)) 
      {
        $sortref = $sortrow;
      }

      $sorders[$sortref.'_up']   = "$sortrow ASC";
      $sorders[$sortref.'_down'] = "$sortrow DESC";
    }
    $this->set_sort_orders($sorders);
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
      $perpage = $this->get_default_per_page();

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
      $sort = $this->get_default_sort_order();

    if (isset($opts['page']) && $opts['page'] > 0)
      $page = $opts['page'];
    else
      $page = 1;

    if (isset($opts['count']) && $opts['count'] > 0)
      $count = $opts['count'];
    else
      $count = $this->get_default_per_page();

    $offset = $count * ($page - 1);

    $sorders = $this->get_sort_orders();

    if (isset($sorders[$sort]))
    {
      $statement = "ORDER BY {$sorders[$sort]} LIMIT $offset, $count";
    }
    else
    {
      $statement = "LIMIT $offset, $count";
    }

#    error_log("pager statement: $statement");
    return $statement;
  }

}
