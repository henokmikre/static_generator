<?php

namespace Drupal\static_generator;

use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Render\RendererInterface;

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
  private $generatorDirectory = '/var/www/sg/static';

  /**
   * The renderer.
   *
   * @var RendererInterface
   */
  private $renderer;

  /**
   * The static generator cache.
   *
   * @var CacheBackendInterface
   */
  private $staticGeneratorCache;

  /**
   * Constructs a new StaticGenerator object.
   *
   * @param RendererInterface $renderer
   *   The renderer.
   * @param CacheBackendInterface $static_generator_cache
   *   The render cache.
   */
  public function __construct(RendererInterface $renderer, $static_generator_cache) {
    $this->renderer = $renderer;
    $this->staticGeneratorCache = $static_generator_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('static_generator_cache')
    );
  }

  /**
   */
  public function generateAll() {
  }

  /**
   * Returns the rendered route.
   *
   * @param Route $route
   *   The route to render.
   *
   * @return String
   *   The rendered markup.
   *
   */
  public function generateRoute(Route $route) {
    $this->renderer->renderRoot($route->getDefaults());

    //    if (empty($route)) {
    //      return;
    //    }
    //$rendered = $this->renderer->
  }
}
