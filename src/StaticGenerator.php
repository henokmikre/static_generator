<?php

namespace Drupal\static_generator;

use DOMDocument;
use DOMXPath;
use Drupal\block\BlockViewBuilder;
use Drupal\Component\Utility\Html;
use Drupal\Core\Site\Settings;
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
use Drupal\Core\Render\Markup;

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
   * @param String $path
   *   The page's path.
   *
   * @return String
   *   The generated markup.
   *
   * @throws \Exception
   */
  public function generatePage($path) {

    // Get/Process markup.
    $markup = $this->getMarkupForPage($path);
    $markup_esi = $this->injectESIs($markup);

    // Write page files.
    $real_path = '';
    if ($path == '/front') {
      $file_name = 'index.html';
    }
    else {
      $file_name = strrchr($path, '/') . '.html';
      $file_name = substr($file_name, 1);
      $occur = substr_count($path, '/');
      if ($occur > 1) {
        $last_pos = strrpos($path, '/');
        $real_path = substr($path, 0, $last_pos);
      }
    }
    $directory = Settings::get('file_private_path') . '/static' . $real_path;
    if (file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($markup_esi, $directory . '/' . $file_name, FILE_EXISTS_REPLACE);
    }
    return $markup_esi;
  }

  /**
   * Returns the rendered markup for a path.
   *
   * @param String $path
   *   The path.
   *
   * @return String
   *   The rendered markup.
   *
   * @throws \Exception When an Exception occurs during processing
   *
   */
  public function getMarkupForPage($path) {

    // Get response for path.
    \Drupal::service('account_switcher')->switchTo(new AnonymousUserSession());
    $request = Request::create($path);
    $request->server->set('SCRIPT_NAME', $GLOBALS['base_path'] . 'index.php');
    $request->server->set('SCRIPT_FILENAME', 'index.php');
    $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);

    // Return markup.
    $markup = $response->getContent();
    \Drupal::service('account_switcher')->switchBack();

    return $markup;

    // Get a response.
    //    $request = Request::create($path);
    //    $request->server->set('SCRIPT_NAME', $GLOBALS['base_path'] . 'index.php');
    //    $request->server->set('SCRIPT_FILENAME', 'index.php');
    //    $response = $this->httpKernel->handle($request);

    //\Drupal::service('account_switcher')->switchBack();
    //\Drupal::service('account_switcher')->switchTo(new AnonymousUserSession());
    //$request = $this->requestStack->getCurrentRequest();
    //$subrequest = Request::create($request->getBaseUrl() . '/node/1', 'GET', array(), $request->cookies->all(), array(), $request->server->all());
    //$response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    //$session_manager = Drupal::service('session_manager');
    //$request->setSession(new AnonymousUserSession());
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
      if ($id == '') {
        continue;
      }
      $block_id = str_replace('-', '_', substr($id, 6));
      if (substr($block_id, 0, 12) == 'views_block_') {
        //str_replace('views_block_', 'views_block__', $block_id);
        $block_id = 'views_block__' . substr($block_id, 12);
      }
      $block_ids_esi = [
        'bartik_branding',
        'views_block__content_recent_block_1',
      ];
      if (!in_array($block_id, $block_ids_esi)) {
        continue;
      }
      $this->generateFragment($block_id);

      $include_markup = '<!--#include virtual="/esi/block/' . Html::escape($block_id) . '" -->';
      //$include_markup = Html::escape('<!--#include virtual="/esi/block/' . $block_id . '" -->');
      $include = $dom->createElement('div', $include_markup);
      $block->parentNode->replaceChild($include, $block);

      $dom->validateOnParse = FALSE;
      $xp = new DOMXPath($dom);
      $col = $xp->query('//div[ @id="toolbar-administration" ]');
      if (!empty($col)) {
        foreach ($col as $node) {
          $node->parentNode->removeChild($node);
        }
      }

      // Admin menu
      //      $admin_menu = $dom->getElementById('toolbar-administration');
      //      if(!empty($admin_menu)){
      //        $body = $dom->getElementsByTagName('body');
      //        $body = $body->item(0);
      //        $body->removeChild($admin_menu);
      //      }

    }
    $markup_esi = $dom->saveHTML();
    $markup_esi = str_replace('&lt;', '<', $markup_esi);
    $markup_esi = str_replace('&gt;', '>', $markup_esi);

    return $markup_esi;
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
    if (empty($block_id)) {
      return;
    }
    $block_render_array = BlockViewBuilder::lazyBuilder($block_id, "full");
    $block_markup = $this->renderer->renderRoot($block_render_array);
    $dir = 'private://static/esi/block';
    if (file_prepare_directory($dir, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($block_markup, 'private://static/esi/block/' . $block_id, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Generate all pages.
   *
   * @return String
   *   The number of pages generated.
   *
   * @throws \Exception
   */
  public function generateAllPages() {
    $front_page = $this->generateFrontPage();
    $this->generateBasicPages();
    return $front_page;
  }

  /**
   * Generate all pages.
   *
   * @return int
   *   The number of pages generated.
   *
   * @throws \Exception
   */
  public function generateBasicPages() {
    $bundle = 'page';
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    //$query->condition('field_name.value', 'default', '=');
    $entity_ids = $query->execute();
    foreach ($entity_ids as $entity_id) {
      $alias = \Drupal::service('path.alias_manager')
        ->getAliasByPath('/node/' . $entity_id);
      $this->generatePage($alias);
    }
    return 0;
  }

  /**
   * Generate front page.
   *
   * @return String
   *   The number of pages generated.
   *
   * @throws \Exception
   */
  public function generateFrontPage() {
    return $this->generatePage('/front');
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

