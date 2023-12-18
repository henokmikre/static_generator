<?php

namespace Drupal\static_generator\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Allows modules to modify the markup.
 */
class ModifyMarkupEvent extends Event {
  protected $markup;
  protected $node;
  protected $path;

  public function __construct($markup, $node, $path) {
    $this->markup = $markup;
    $this->node = $node;
    $this->path = $path;
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

  /**
   * Returns the path/alias of the node.
   *
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

  public function setMarkup($markup) {
    $this->markup = $markup;
  }

}