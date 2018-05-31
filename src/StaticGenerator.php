<?php

namespace Drupal\static_generator;

use DOMDocument;
use DOMXPath;
use Drupal\block\BlockViewBuilder;
use Drupal\Component\Utility\Html;
use Drupal\static_generator\Render\Placeholder\StaticGeneratorStrategy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AnonymousUserSession;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Route;
use Drupal\Core\Render\MainContent\HtmlRenderer;

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
  private $generatorDirectory = '/var/www/sg/private/static';

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
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

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
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP Kernel service.
   *
   */
  public function __construct(CacheBackendInterface $static_generator_cache, RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, array $main_content_renderers, RequestStack $request_stack, HttpKernelInterface $http_kernel) {
    $this->staticGeneratorCache = $static_generator_cache;
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->mainContentRenderers = $main_content_renderers;
    $this->requestStack = $request_stack;
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    return $events;
  }

  /**
   * Generate markup for a single page.
   *
   * @param int $nid
   *   The node id.
   *
   * @return String
   *   The generated markup.
   *
   * @throws \Exception
   */
  public function generatePage($nid = 1) {
    $markup = $this->getMarkupForPage($nid);
    $markup_esi = $this->injectESIs($markup);
    if($nid == 0) {
      $filename = 'private://static/index.htm';
    } else {
      $filename = 'private://static/node/' . $nid;
    }

    file_unmanaged_save_data($markup_esi,  $filename, FILE_EXISTS_REPLACE);
    return $markup_esi;
  }

  /**
   * Returns the rendered markup for a node.
   *
   * @param int $nid
   *   The node id.
   *
   * @return String
   *   The rendered markup.
   *
   * @throws \Exception When an Exception occurs during processing
   *
   */
  public function getMarkupForPage($nid = 1) {
    if ($nid == 0) {
      $request = Request::create('/node');
    }
    else {
      $request = Request::create('/node/' . $nid);
    }
    $response = $this->httpKernel->handle($request);

    // Return markup.
    $markup = $response->getContent();
    return $markup;



    //$request = $this->requestStack->getCurrentRequest();
    //$subrequest = Request::create($request->getBaseUrl() . '/node/1', 'GET', array(), $request->cookies->all(), array(), $request->server->all());

    //$response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    //\Drupal::service('account_switcher')->switchTo(new AnonymousUserSession());
    //$session_manager = Drupal::service('session_manager');
    //$request->setSession(new AnonymousUserSession());
    //Drupal::service('account_switcher')->switchBack();
  }

  /**
   * Inject ESI markup for every block.
   *
   * @param String $markup
   *   The markup.
   *
   * @return String
   *   Markup with ESI's injected.
   *
   * @throws \Exception
   */
  public function injectESIs($markup) {
    $dom = new DomDocument();
    @$dom->loadHTML($markup);
    $finder = new DomXPath($dom);
    $classname = "block";
    $blocks = $finder->query("//div[contains(@class, '$classname')]");
    foreach ($blocks as $block) {
      $id = $block->getAttribute('id');
      if($id == '') {
        continue;
      }
      $block_id = str_replace('-', '_', substr($id, 6));
      if (substr($block_id, 0, 12) == 'views_block_') {
        //str_replace('views_block_', 'views_block__', $block_id);
        $block_id = 'views_block__' . substr($block_id, 12);
      }
      $block_ids_esi = ['bartik_branding', 'views_block__content_recent_block_1', ];
      if(!in_array($block_id, $block_ids_esi )) {
        continue;
      }
      $this->generateFragment($block_id);
      $include_markup = '<!--#include virtual="/esi/block/' . Html::escape($block_id) . '" -->';
      $include = $dom->createElement('span', $include_markup);
      $block->parentNode->replaceChild($include, $block);
    }
    return $dom->saveHTML();
  }

  /**
   * Generate a fragment file.
   *
   * @param string $block_id
   *   The block id.
   *
   * @return boolean
   *   The file was successfully generated.
   *
   * @throws \Exception
   */
  public function generateFragment($block_id) {

    if(empty($block_id)) {
      return;
    }
    $block_render_array = BlockViewBuilder::lazyBuilder($block_id, "full");
    $block_markup = $this->renderer->render($block_render_array);
    file_unmanaged_save_data($block_markup, 'private://esi/block/' . $block_id, FILE_EXISTS_REPLACE);
  }

  /**
   * Generate all pages.
   *
   * @return int
   *   The number of pages generated.
   *
   * @throws \Exception
   */
  public function generateAllPages() {
    //$this->generateFrontPage();
    $this->generatePage(1);
    return 0;
  }

  /**
   * Generate front page.
   *
   * @throws \Exception
   */
  public function generateFrontPage() {
    $this->generatePage(0);
  }

}


//    $entity_type = 'node';
//    $view_mode = 'full';
//    $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load(1);
//    $node_render_array = \Drupal::entityTypeManager()
//      ->getViewBuilder($entity_type)
//      ->view($node, $view_mode);
//    $request = new Request();
//
//    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
//    $response = $renderer->renderResponse($node_render_array, $request, $this->routeMatch);
//    $content = $response->getContent();
//    return $content;
//
//  }
//$response = $this->htmlRenderer->renderResponse($node_render_array, $request, $this->routeMatch);
//    $render = $this->renderer->render($render_array, NULL, NULL);
//    $render_root = $this->renderer->renderRoot($render_array, NULL, NULL);
//    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
//    $response = $renderer->renderResponse($render_array, NULL, $this->routeMatch);
//    $entity_type_id = $node->getEntityTypeId();
//$output = render(\Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($node, $view_mode));
//$rendered = $this->renderer->

