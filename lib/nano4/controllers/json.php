<?php

namespace Nano4\Controllers;

/**
 * JSON related methods.
 */
Trait JSON
{
  /**
   * An easy way to add JSON data to the templates.
   *
   * In your layout, you should have a section that looks like:
   *
   * <?php
   *   if (isset($json) && count($json) > 0)
   *     foreach ($json as $json_name => $json_data)
   *       echo $html->json($json_name, $json_data);
   * ?>
   *
   * The above assumes you're using the Messages trait, which should be loaded
   * after this one, and includes the $html template helper. You could also
   * choose to build the JSON fields manually, but the helper is simpler.
   */
  public function add_json ($name, $data)
  {
    $this->data['json'][$name] = $data;
  }

}
