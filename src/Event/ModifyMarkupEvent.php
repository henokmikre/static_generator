<?php

namespace Drupal\static_generator\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Allows modules to modify the markup.
 */
class ModifyMarkupEvent extends Event {
  protected $markup;
  protected $node;

  public function __construct($markup, $node) {
    $this->markup = $markup;
    $this->node = $node;
  }

  public function getMarkup() {
    return $this->markup;
  }

  /**
   * Returns the node object in case modules need to act based on properties.
   *
   * @return node
   */
  public function getNode() {
    return $this->node;
  }

  public function setMarkup($markup) {
    $this->markup = $markup;
  }

}