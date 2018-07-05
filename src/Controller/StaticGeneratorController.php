<?php

namespace Drupal\static_generator\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for StaticGenerator module routes.
 */
class StaticGeneratorController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function generate() {
    $build = [
      '#markup' => \Drupal::service('static_generator')->generatePages(),
      ];
    return $build;
  }

}
