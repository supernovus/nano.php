<?php

namespace Nano\Meta;

Trait ClassID
{
  /**
   * The constructor will be passed a '__classid' option.
   * Ensure this property is populated with its value.
   */
  protected $__classid;

  public function class_id ()
  {
    return $this->__classid;
  }
}

