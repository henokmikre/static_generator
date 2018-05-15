<?php

namespace Drupal\static_generator;

use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;


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
   * The static generator cache.
   *
   * @var CacheBackendInterface
   */
  private $staticGeneratorCache;

  /**
   * The renderer.
   *
   * @var RendererInterface
   */
  private $renderer;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The class resolver.
   *
   * @var ClassResolverInterface
   */
  private $classResolver;

  /**
   * The html renderer.
   *
   * @var HtmlRenderer
   *
   */
  protected $htmlRenderer;

  /**
   * Constructs a new StaticGenerator object.ClassResolverInterface
   * $class_resolver,
   *
   * @param CacheBackendInterface $static_generator_cache
   *   The cache for statically generated pages.
   * @param RendererInterface $renderer
   *  The renderer.
   * @param RouteMatchInterface $route_match
   *   The route matcher.
   * @param ClassResolverInterface $class_resolver
   *  The class resolver.
   * @param htmlRenderer $html_renderer
   *  The main content renderer.
   *
   */
  public function __construct(CacheBackendInterface $static_generator_cache, RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, htmlRenderer $html_renderer) {
    $this->staticGeneratorCache = $static_generator_cache;
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->htmlRenderer = $html_renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('static_generator_cache'),
      $container->get('renderer'),
      $container->get('route_match'),
      $container->get('class_resolver'),
      $container->get('main_content_renderers')
    );
  }

  /**
   */
  public function generateAll() {
  }

  /**
   * Returns the rendered markup for a route.
   *
   * @param int $nid
   *   The node id to render.
   *
   * @return String
   *   The rendered markup.
   *
   */
  public function generateStaticMarkupForPage($nid = 1) {
    $entity_type = 'node';
    $view_mode = 'full';
    $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($nid);
    $render_array = \Drupal::entityTypeManager()
      ->getViewBuilder($entity_type)
      ->view($node, $view_mode);
    $request = new Request();

    $response = $this->htmlRenderer->renderResponse($render_array, $request, $this->routeMatch);

//    $render = $this->renderer->render($render_array, NULL, NULL);
//    $render_root = $this->renderer->renderRoot($render_array, NULL, NULL);
//    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
//    $response = $renderer->renderResponse($render_array, NULL, $this->routeMatch);
//
//
//    $entity_type_id = $node->getEntityTypeId();
    return NULL;

    //$output = render(\Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($node, $view_mode));
    //$rendered = $this->renderer->
  }

}
