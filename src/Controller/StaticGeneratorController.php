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
      '#markup' => \Drupal::service('static_generator')->blockIds(),
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
      \Drupal::service('static_generator')->generatePage('/node/' . $nid, FALSE, TRUE);
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
