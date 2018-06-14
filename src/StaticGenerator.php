<?php

namespace Drupal\static_generator;

use DOMXPath;
use DOMDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\block\BlockViewBuilder;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Static Generator Service.
 *
 * Manages static generation process.
 */
class StaticGenerator implements EventSubscriberInterface {

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
   * The webform theme manager.
   *
   * @var \Drupal\webform\WebformThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme initialization.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitialization;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new StaticGenerator object.ClassResolverInterface
   * $class_resolver,
   *
   * @param RendererInterface $renderer
   *  The renderer.
   * @param RouteMatchInterface $route_match
   *   The route matcher.
   * @param ClassResolverInterface $class_resolver
   *  The class resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP Kernel service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization
   *   The theme initialization.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   *
   */
  public function __construct(RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, RequestStack $request_stack, HttpKernelInterface $http_kernel, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization, ConfigFactoryInterface $config_factory) {
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->requestStack = $request_stack;
    $this->httpKernel = $http_kernel;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
    $this->configFactory = $config_factory;
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

    // Return if path is excluded.
    $paths_do_not_generate_string = $this->configFactory->get('static_generator.settings')->get('paths_do_not_generate');
    if(!empty($paths_do_not_generate_string)){
      $paths_do_not_generate = explode(',', $paths_do_not_generate_string );
      if(in_array($path, $paths_do_not_generate)) {
        return;
      }
    }

    // Get/Process markup.
    $markup = $this->getMarkupForPage($path);
    $markup_esi = $this->injectESIs($markup);

    // Write page files.
    $real_path = '';
    $front = $this->configFactory->get('system.site')->get('page.front');
    if ($path == $front) {
      $file_name = 'index.html';
    }
    else {
      $alias = \Drupal::service('path.alias_manager')
        ->getAliasByPath($path);
      $file_name = strrchr($alias, '/') . '.html';
      $file_name = substr($file_name, 1);
      $occur = substr_count($alias, '/');
      if ($occur > 1) {
        $last_pos = strrpos($alias, '/');
        $real_path = substr($alias, 0, $last_pos);
      }
    }
    $directory = $this->configFactory->get('static_generator.settings')->get('generator_directory') . $real_path;
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

    // Generate as Anonymous user.
    \Drupal::service('account_switcher')->switchTo(new AnonymousUserSession());

    // Save active theme.
    $active_theme = $this->themeManager->getActiveTheme();

    // Switch to default theme.
    $default_theme_name = $this->configFactory->get('system.theme')
      ->get('default');
    $default_theme = $this->themeInitialization->getActiveThemeByName($default_theme_name);
    $this->themeManager->setActiveTheme($default_theme);

    $request = Request::create($path);
    //$request->server->set('SCRIPT_NAME', $GLOBALS['base_path'] . 'index.php');
    //$request->server->set('SCRIPT_FILENAME', 'index.php');
    $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, FALSE);

    // Return markup.
    $markup = $response->getContent();

    // Switch back to active theme.
    $this->themeManager->setActiveTheme($active_theme);

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
    $classname = 'block';
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
      $include = $dom->createElement('span', $include_markup);
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
    $dir = $this->configFactory->get('static_generator.settings')->get('generator_directory') . '/esi/block';
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
    $this->generateNodes();
    $this->generatePaths();
  }

  /**
   * Generate nodes.
   *
   * @return int
   *   The number of pages generated.
   *
   * @throws \Exception
   */
  public function generateNodes() {

    // Get bundles to generate from config.
    $gen_node_bundles_string = $this->configFactory->get('static_generator.settings')
      ->get('gen_node');
    $gen_node_bundles = explode(',', $gen_node_bundles_string);

    // Generate each bundle
    $entity_ids =[];
    foreach ($gen_node_bundles as $bundle) {
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      //$query->condition('field_name.value', 'default', '=');
      $entity_ids = $query->execute();
    }

    foreach ($entity_ids as $entity_id) {
      $this->generatePage('/node/' . $entity_id);
    }
    return 0;
  }

  /**
   * Generate paths.
   *
   * @throws \Exception
   */
  public function generatePaths() {
    $paths_string = $this->configFactory->get('static_generator.settings')->get('paths_generate');
    if(!empty($paths_string)) {
      $paths = explode(',', $paths_string );
      foreach($paths as $path) {
        $this->generatePage($path);
      }
    }
  }

}


//    $entity_type = 'node';
//    $view_mode = 'full';
//    $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load(1);
//    $node_render_array = \Drupal::entityTypeManager()
//      ->getViewBuilder($entity_type)
//      ->view($node, $view_mode);
//
//$response = $this->htmlRenderer->renderResponse($node_render_array, $request, $this->routeMatch);
//    $render = $this->renderer->render($render_array, NULL, NULL);
//    $render_root = $this->renderer->renderRoot($render_array, NULL, NULL);
//    $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);
//    $response = $renderer->renderResponse($render_array, NULL, $this->routeMatch);
//    $entity_type_id = $node->getEntityTypeId();
//$output = render(\Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($node, $view_mode));

