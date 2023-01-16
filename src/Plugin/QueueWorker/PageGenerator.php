<?php

namespace Drupal\static_generator\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate queued pages.
 *
 * @QueueWorker(
 *   id = "page_generator",
 *   title = @Translation("Generates the pages in the queue."),
 *   cron = {"time" = 60},
 * )
 */
class PageGenerator extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Static Generator service.
   *
   * @var \Drupal\static_generator\StaticGenerator
   */
  protected $staticGenerator;

  /**
   * Constructs a new PageProcessor object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param $static_generator
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $static_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->staticGenerator = $static_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('static_generator')
    );
  }

  /**
   * {@inheritdoc}
   * @param $item
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Theme\MissingThemeDependencyException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function processItem($item) {
    // If called by StaticGenerator::processQueue, queue object is sent, which has [data], [created], [item_id].
    // If called by cron, only [path] and [path_generate] are sent.

    $path = '';
    $path_generate = '';
    $action = 'create'; // default behavior is to create a page.
    $empty_array = []; // For simplicity.

    try {
      if (isset($item->data)) {
        $path = $item->data->path;

        if (isset($item->data->path_generate)) {
          $path_generate = $item->data->path_generate;
        }

        if (isset($item->data->action)) {
          $action = $item->data->action;
        }

        if ($action == 'delete') {
          $this->staticGenerator->deletePage($path);
        } else {
          if (empty($path_generate)) {
            $this->staticGenerator->generatePage($path, '', false, false, true, true, $empty_array, $empty_array, $empty_array, true);
          } else {
            $this->staticGenerator->generatePage($path, $path_generate, false, false, true, true, $empty_array, $empty_array, $empty_array, true);
          }
        }
      } elseif (isset($item->path)) {
        $path = $item->path;

        if (isset($item->path_generate)) {
          $path_generate = $item->path_generate;
        }

        if (isset($item->action)) {
          $action = $item->action;
        }

        if ($action == 'delete') {
          $this->staticGenerator->deletePage($path);
        } else {
          if (empty($path_generate)) {
            $this->staticGenerator->generatePage($path, '', false, false, true, true, $empty_array, $empty_array, $empty_array, true);
          } else {
            $this->staticGenerator->generatePage($path, $path_generate, false, false, true, true, $empty_array, $empty_array, $empty_array, true);
          }
        }
      }
    } catch (Exception $e) {
      \Drupal::logger('static_generator')->error('%msg', array('%msg' => $e->getMessage()));
    }
  }
}
