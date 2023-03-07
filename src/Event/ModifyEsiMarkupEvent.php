<?php

namespace Drupal\static_generator\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Allows modules to modify the markup.
 */
class ModifyEsiMarkupEvent extends Event {
  protected $markup;

  public function __construct($markup) {
    $this->markup = $markup;
  }

  public function getMarkup() {
    return $this->markup;
  }

  public function setMarkup($markup) {
    $this->markup = $markup;
  }

}