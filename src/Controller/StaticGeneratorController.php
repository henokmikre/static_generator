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
  public function generateStaticMarkupForPage() {
    $build = [
      '#markup' => \Drupal::service('static_generator')->generateStaticMarkupForPage(),
      ];
    return $build;
  }

}
