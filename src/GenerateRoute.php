<?php

namespace Drupal\static_generator;

/**
 * Member Profile Service for Tally.
 *
 * Interface to the Tally REST member profile services.
 */
class GenerateRoute {

  /**
   * The target directory.
   *
   * @var string
   */
  private $generatorDirectory;

  /**
   * The Constructor for the class.
   *
   * @inheritdoc
   */
  public function __construct(string $generatorDirectory) {
    $this->generatorDirectory = $generatorDirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function generate(Route $route = NULL) {

    if (empty($route)) {
      //$account = $this->currentUser;
    }

  }
}
