<?php

namespace Drupal\static_generator;

use DOMXPath;
use DOMDocument;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
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
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Static Generator Service.
 *
 * Manages static generation process.
 */
class StaticGenerator implements EventSubscriberInterface {

  /**
   * File permission check -- File is readable.
   */
  const BLOCK_IDS_ESI = [
    'bartik_branding',
    //'views_block__content_recent_block_1',
  ];

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
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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
  public function __construct(RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, RequestStack $request_stack, HttpKernelInterface $http_kernel, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization, ConfigFactoryInterface $config_factory, FileSystemInterface $file_system) {
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->requestStack = $request_stack;
    $this->httpKernel = $http_kernel;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    //    $events[KernelEvents::REQUEST][] = array('onKernelRequestPathResolve', 100);
    //    return $events;
  }

  /**
   * Generate markup for a single page.
   *
   * @param String $path
   *   The page's path.
   *
   * @param $generate_blocks
   *   Generate the block fragments referenced by the ESI's.
   *
   * @throws \Exception
   */
  public function generatePage($path, $generate_blocks = FALSE) {

    // Return if path is excluded.
    $paths_do_not_generate_string = $this->configFactory->get('static_generator.settings')
      ->get('paths_do_not_generate');
    if (!empty($paths_do_not_generate_string)) {
      $paths_do_not_generate = explode(',', $paths_do_not_generate_string);
      $path_alias = \Drupal::service('path.alias_manager')
        ->getAliasByPath($path);
      if (in_array($path_alias, $paths_do_not_generate)) {
        return;
      }
    }

    // Get/Process markup.
    $markup = $this->markupForPage($path);
    $markup_esi = $this->injectESIs($markup, $generate_blocks);

    // Write page files.
    $web_directory = $this->directoryFromPath($path);
    $file_name = $this->filenameFromPath($path);
    $generator_directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    $directory = $generator_directory . $web_directory;
    if (file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($markup_esi, $directory . '/' . $file_name, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Get filename from path.
   *
   * @param String $path
   *   The page's path.
   *
   * @return String
   *   The filename.
   *
   * @throws \Exception
   */
  public function filenameFromPath($path) {
    $front = $this->configFactory->get('system.site')->get('page.front');
    if ($path == $front) {
      $file_name = 'index.html';
    }
    else {
      $alias = \Drupal::service('path.alias_manager')
        ->getAliasByPath($path);
      $file_name = strrchr($alias, '/') . '.html';
      $file_name = substr($file_name, 1);
    }
    return $file_name;
  }

  /**
   * Get page directory from path.
   *
   * @param String $path
   *   The page's path.
   *
   * @return String
   *   The directory and filename.
   *
   * @throws \Exception
   */
  public function directoryFromPath($path) {
    $directory = '';
    $front = $this->configFactory->get('system.site')->get('page.front');
    if ($path <> $front) {
      $alias = \Drupal::service('path.alias_manager')
        ->getAliasByPath($path);
      $occur = substr_count($alias, '/');
      if ($occur > 1) {
        $last_pos = strrpos($alias, '/');
        $directory = substr($alias, 0, $last_pos);
      }
    }
    return $directory;
  }

  /**
   * Generate markup for a single page.
   *
   * @param String $path
   *   The page's path.
   *
   * @return boolean
   *   A file was deleted.
   *
   * @throws \Exception
   */
  public function deletePage($path) {
    $web_directory = $this->directoryFromPath($path);
    $file_name = $this->filenameFromPath($path);
    $config_directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    file_unmanaged_delete($config_directory . $web_directory . '/' . $file_name);
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
  public function markupForPage($path) {

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
   * @param bool $generate_blocks
   *   Generate the block fragments referenced by the ESI's.
   *
   * @return String
   *   Markup with ESI's injected.
   *
   * @throws \Exception
   */
  public function injectESIs($markup, $generate_blocks = FALSE) {
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

      if ($generate_blocks) {
        $this->generateBlock($block_id);
      }

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
    }
    $markup_esi = $dom->saveHTML();
    $markup_esi = str_replace('&lt;', '<', $markup_esi);
    $markup_esi = str_replace('&gt;', '>', $markup_esi);

    return $markup_esi;
  }

  /**
   * Generate all block fragment files.
   *
   * @throws \Exception
   */
  public function generateBlocks() {
    foreach ($this::BLOCK_IDS_ESI as $block_id) {
      $this->generateBlock($block_id);
    }
  }

  /**
   * Generate a block fragment file.
   *
   * @param string $block_id
   *   The block id.
   *
   * @throws \Exception
   */
  public function generateBlock($block_id) {
    if (empty($block_id)) {
      return;
    }
    $block_render_array = BlockViewBuilder::lazyBuilder($block_id, "full");
    $block_markup = $this->renderer->renderRoot($block_render_array);
    $dir = $this->configFactory->get('static_generator.settings')
        ->get('generator_directory') . '/esi/block';
    if (file_prepare_directory($dir, FILE_CREATE_DIRECTORY)) {
      //file_unmanaged_save_data($block_markup, 'private://static/esi/block/' . $block_id, FILE_EXISTS_REPLACE);
      file_unmanaged_save_data($block_markup, $dir . '/' . $block_id, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Generate all pages/blocks/files.
   *
   * @throws \Exception
   */
  public function generateAll() {
    //$this->wipeFiles();
    $this->generatePages();
    $this->generateBlocks();
    //$this->generateFiles();
  }

  /**
   * Generate pages.
   *
   * @throws \Exception
   */
  public function generatePages() {
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
    foreach ($gen_node_bundles as $bundle) {
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      $entity_ids = $query->execute();
      foreach ($entity_ids as $entity_id) {
        //$node = \Drupal::entityTypeManager()->getStorage('node')->load($entity_id);
        //$node->set('moderation_state', 'published');
        //$node->save();
        $this->generatePage('/node/' . $entity_id);
      }
    }
  }

  /**
   * Generate config paths.
   *
   * @throws \Exception
   */
  public function generatePaths() {
    $paths_string = $this->configFactory->get('static_generator.settings')
      ->get('paths_generate');
    if (!empty($paths_string)) {
      $paths = explode(',', $paths_string);
      foreach ($paths as $path) {
        $this->generatePage($path);
      }
    }
  }

  /**
   * Delete all generated pages and files.
   *
   * @throws \Exception
   */
  public function wipeFiles() {
    $directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    file_unmanaged_delete_recursive($directory, $callback = NULL);
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
  }

  /**
   * Exclude media that is not published (e.g. Draft or Archived).
   *
   * @throws \Exception
   */
  public function excludeMediaIds() {
    $query = \Drupal::entityQuery('media');
    $query->condition('status', 0);
    $exclude_media_ids = $query->execute();
    return $exclude_media_ids;
  }

  /**
   * Generate all files.
   *
   * @throws \Exception
   */
  public function generateFiles() {
    $this->generateCodeFiles();
    $this->generatePublicFiles();
  }

  /**
   * Generate public files.
   *
   * @throws \Exception
   */
  public function generatePublicFiles() {

    // Files to exclude.
    $exclude_media_ids = $this->excludeMediaIds();
    $exclude_files = '';
    foreach ($exclude_media_ids as $exclude_media_id) {
      $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($exclude_media_id);
      $fid = $media->get('field_media_image')->getValue()[0]['target_id'];
      $file = File::load($fid);
      $url = Url::fromUri($file->getFileUri());
      $uri = $url->getUri();
      $exclude_file = substr($uri, 9);
      $exclude_files .= $exclude_file . "\r\n";
    }
    file_unmanaged_save_data($exclude_files, 'private://static/exclude_files.txt', FILE_EXISTS_REPLACE);

    // Create files directory if it does not exist.
    exec('mkdir -p /var/www/sg/private/static/sites/default/files');
    //exec('chmod -R 777 /var/www/sg/private/static/sites/default/files');

    $public_files_directory = $this->fileSystem->realpath('public://');
    $generator_directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    $generator_directory = $this->fileSystem->realpath($generator_directory);

    // rSync
    $rsync_public = $this->configFactory->get('static_generator.settings')
      ->get('rsync_public');
    $public_files = 'rsync -zr --delete --delete-excluded ' . $rsync_public . ' --exclude-from "' . $generator_directory . '/exclude_files.txt" ' . $public_files_directory . ' ' . $generator_directory . '/sites/default';
    exec($public_files);

  }

  /**
   * Generate core/modules/themes files.
   *
   * @throws \Exception
   */
  public function generateCodeFiles() {

    $generator_directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    $generator_directory = $this->fileSystem->realpath($generator_directory);
    $rsync_code = $this->configFactory->get('static_generator.settings')
      ->get('rsync_code');

    // rSync core.
    $core_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/core ' . $generator_directory;
    exec($core_files);

    // rSync modules.
    $module_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/modules ' . $generator_directory;
    exec($module_files);

    // rSync themes.
    $theme_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/themes ' . $generator_directory;
    exec($theme_files);

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

