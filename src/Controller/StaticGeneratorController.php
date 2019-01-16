<?php

namespace Drupal\static_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

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
      //'#markup' => \Drupal::service('static_generator')->generatePage('/node/158364'),
      '#markup' => \Drupal::service('static_generator')->generatePage('/node/175312'),
      //'#markup' => \Drupal::service('static_generator')->generatePage('/patent'),
      //'#markup' => \Drupal::service('static_generator')->generatePage('/node/187833'),
      //'#markup' => \Drupal::service('static_generator')->generatePage('/patent', FALSE, TRUE),
      //'#markup' => \Drupal::service('static_generator')->processQueue(),
      //'#markup' => \Drupal::service('static_generator')->generateNodes('page',FALSE, 0, 2),
      //'#markup' => \Drupal::service('static_generator')->generatePages(),
      //'#markup' => \Drupal::service('static_generator')->generateNodes('bio'),
      //'#markup' => \Drupal::service('static_generator')->generateBlocks(TRUE),
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
    try {
      \Drupal::service('static_generator')
        ->generatePage('/node/' . $nid, FALSE, TRUE);
    } catch (\Exception $exception) {
    }

    $build = [
      '#markup' => $this->t('Page generation complete.'),
    ];
    return $build;
  }

  /**
   * Static generation info for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return array
   * The markup.
   */
  public function generationInfoNode(NodeInterface $node) {
    $build = [
      '#markup' => \Drupal::service('static_generator')
        ->generationInfo('/node/' . $node->id()),
    ];
    return $build;
  }

}
