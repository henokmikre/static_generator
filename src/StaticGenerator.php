<?php

namespace Drupal\static_generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use Drupal\Core\Render\MainContent\HtmlRenderer;
use Symfony\Component\Routing\Route;

/**
 * Static Generator Service.
 *
 * Manages static generation process.
 */
class StaticGenerator implements EventSubscriberInterface {

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
   * The available main content renderer services, keyed per format.
   *
   * @var array
   */
  protected $mainContentRenderers;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
   * @param array $main_content_renderers
   *   The available main content renderer service IDs, keyed by format.
   * @param array $request_stack
   *   The request stack.
   *
   */
  public function __construct(CacheBackendInterface $static_generator_cache, RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, array $main_content_renderers, RequestStack $request_stack) {
    $this->staticGeneratorCache = $static_generator_cache;
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->mainContentRenderers = $main_content_renderers;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    //$events[KernelEvents::REQUEST][] = ['generateStaticMarkupForPage'];
    //$a = 'foo';
    $events = [];
    return $events;
  }

  /**
   * Write static file.
   *
   */
  public function writePage() {
  }

  /**
   * Generate a single page.
   *
   * @return int
   *   The number of pages generated.
   *
   */
  public function generatePage() {
    $foo = $this->generatorDirectory;
  }

  /**
   * Generate all pages.
   *
   * @return int
   *   The number of pages generated.
   *
   */
  public function generateAllPages() {
  }

  /**
   * Returns the rendered markup for a node.
   *
   * @return String
   *   The rendered markup.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *
   */
  public function generateStaticMarkupForPage(GetResponseEvent $event) {

    $a = 'foo';

    $entity_type = 'node';
    $view_mode = 'full';
    $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load(1);
    $node_render_array = \Drupal::entityTypeManager()
      ->getViewBuilder($entity_type)
      ->view($node, $view_mode);
    $request = new Request();

    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
    $response = $renderer->renderResponse($node_render_array, $request, $this->routeMatch);
    $content = $response->getContent();
    return $content;

  }
}


//$response = $this->htmlRenderer->renderResponse($node_render_array, $request, $this->routeMatch);
//    $render = $this->renderer->render($render_array, NULL, NULL);
//    $render_root = $this->renderer->renderRoot($render_array, NULL, NULL);
//    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
//    $response = $renderer->renderResponse($render_array, NULL, $this->routeMatch);
//    $entity_type_id = $node->getEntityTypeId();
//$output = render(\Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($node, $view_mode));
//$rendered = $this->renderer->