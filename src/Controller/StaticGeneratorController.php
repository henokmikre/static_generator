<?php

namespace Drupal\static_generator\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for StaticGenerator module routes.
 */
class StaticGeneratorController extends ControllerBase {

  /**
   * Test route for debugging.
   *
   * @return array
   */
  public function sgTest() {
    $build = [
//      '#markup' => \Drupal::service('static_generator')->fileInfo('/front'),
      '#markup' => \Drupal::service('static_generator')->deletePages(),
      ];
    return $build;
  }

  /**
   * Generate a specified node page.
   *
   * @param $nid
   * The node id.
   *
   * @return array
   * The markup.
   */
  public function generateNode($nid) {
    \Drupal::service('static_generator')->generatePage('/node/' . $nid, TRUE );
    $build = [
      '#markup' => $this->t('Page generation complete.'),
    ];
    return $build;
  }
}
