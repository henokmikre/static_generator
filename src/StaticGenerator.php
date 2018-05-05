<?php

namespace Drupal\static_generator;

use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Static Generator Service.
 *
 * Manages static generation process.
 */
class StaticGenerator {

  /**
   * The generator directory.
   *
   * @var string
   */
  private $generatorDirectory = '/var/www/p8/static';

  /**
   * The render cache.
   *
   * @var RenderCacheInterface
   */
  private $renderCache;

  /**
   * The static generator cache.
   *
   * @var CacheBackendInterface
   */
  private $staticGeneratorCache;

  /**
   * The renderer config.
   *
   * @var array rendererConfig
   */
  private $rendererConfig;

  /**
   * Constructs a new StaticGenerator object.
   *
   * @param string $generatorDirectory
   *   The static generator target directory.
   * @param RenderCacheInterface $render_cache
   *   The render cache.
   * @param CacheBackendInterface $static_generator_cache
   *   The render cache.
   */
  public function __construct($render_cache, $static_generator_cache) {
    $this->renderCache = $render_cache;
    $this->staticGeneratorCache = $static_generator_cache;
  }

  /**
   * {@inheritdoc}
   */
//  public static function create(ContainerInterface $container) {
//    return new static(
//      '/sites/default/files/static_generator',
//      $container->get('render_cache'),
//      $container->get('render_config'),
//      $container->get('cache.default')
//    );
//  }

  /**
   * {@inheritdoc}
   */
  public function generateAll() {
    if (empty($route)) {
      //$account = $this->currentUser;
    }
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
