<?php

namespace Drupal\static_generator;

use DOMXPath;
use DOMDocument;
use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
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

/**
 * Static Generator Service.
 *
 * Manages static generation process.
 */
class StaticGenerator {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, RequestStack $request_stack, HttpKernelInterface $http_kernel, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization, ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager) {
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
    $this->classResolver = $class_resolver;
    $this->requestStack = $request_stack;
    $this->httpKernel = $http_kernel;
    $this->themeManager = $theme_manager;
    $this->themeInitialization = $theme_initialization;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Generate all pages and files.
   *
   * @param int $limit
   * Limit the number of nodes generated for each bundle.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateAll() {
    \Drupal::logger('static_generator')->notice('Begin generateAll()');
    $elapsed_time = $this->deleteAll();
    $elapsed_time += $this->generatePages();
    $elapsed_time += $this->generateFiles();
    \Drupal::logger('static_generator')
      ->notice('End generateAll(), elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Generate pages.
   *
   * @param int $limit
   * Limit the number of nodes generated for each bundle.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generatePages($limit = 0) {
    \Drupal::logger('static_generator')->notice('Begin generatePages()');
    $elapsed_time = $this->deletePages();
    $elapsed_time += $this->generateNodes($limit);
    $elapsed_time += $this->generatePaths();
    $elapsed_time += $this->generateBlocks();
    $elapsed_time += $this->generateRedirects();
    \Drupal::logger('static_generator')
      ->notice('End generatePages(), elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Generate nodes.
   *
   * @param int $limit
   * Limit the number of nodes generated for each bundle.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateNodes($limit = 0) {
    $elapsed_time_total = 0;

    // Get bundles to generate from config.
    $gen_node_bundles_string = $this->configFactory->get('static_generator.settings')
      ->get('gen_node');
    $gen_node_bundles = explode(',', $gen_node_bundles_string);

    // Generate each bundle
    foreach ($gen_node_bundles as $bundle) {
      $start_time = time();
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      if (!empty($limit)) {
        $limit = intval($limit);
        $query->range(1, $limit);
      }

      $entity_ids = $query->execute();
      //$count = count($entity_ids);
      $count = 0;

      // Generate pages for bundle.
      foreach ($entity_ids as $entity_id) {
        //        $node = \Drupal::entityTypeManager()
        //          ->getStorage('node')
        //          ->load($entity_id);
        //        $node->set('moderation_state', 'published');
        //        $node->save();
        $this->generatePage('/node/' . $entity_id);
        $count++;
      }

      // Elapsed time.
      $end_time = time();
      $elapsed_time = $end_time - $start_time;
      \Drupal::logger('static_generator')
        ->notice('generateNodes() bundle: ' . $bundle . ', count: ' . $count . ', elapsed time: ' . $elapsed_time . ' seconds.');
      $elapsed_time_total += $elapsed_time;
    }
    return $elapsed_time_total;
  }

  /**
   * Generate paths specified in settings.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generatePaths() {
    $start_time = time();

    $paths_string = $this->configFactory->get('static_generator.settings')
      ->get('paths_generate');
    if (!empty($paths_string)) {
      $paths = explode(',', $paths_string);
      foreach ($paths as $path) {
        $this->generatePage($path);
      }
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('generatePaths() elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
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
    $directory = $this->generatorDirectory() . $web_directory;
    if (file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($markup_esi, $directory . '/' . $file_name, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Generate all block fragment files.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateBlocks() {
    $start_time = time();

    $blocks_esi = $this->configFactory->get('static_generator.settings')
      ->get('blocks_esi');
    if (empty($blocks_esi)) {
      // Get all block id's
      $storage = $this->entityTypeManager->getStorage('block');
      $ids = $storage->getQuery()
        ->execute();
      $blocks_esi = $storage->loadMultiple($ids);
    }
    else {
      $blocks_esi = explode(',', $blocks_esi);
    }
    foreach ($blocks_esi as $block_id) {
      $this->generateBlock($block_id);
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateBlocks() elapsed time: ' . $elapsed_time . ' seconds.');
    }
    return $elapsed_time;
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
    if ($block_id instanceof Block) {
      $block_id = $block_id->id();
    }

    // Return if block on "no esi" in settings.
    $blocks_no_esi = $this->configFactory->get('static_generator.settings')
      ->get('blocks_no_esi');
    $blocks_no_esi = explode(',', $blocks_no_esi);
    if (in_array($block_id, $blocks_no_esi)) {
      return;
    }

    $block_render_array = BlockViewBuilder::lazyBuilder($block_id, "full");
    $block_markup = $this->renderer->renderRoot($block_render_array);
    $dir = $this->generatorDirectory() . '/esi/block';
    if (file_prepare_directory($dir, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($block_markup, $dir . '/' . $block_id, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Generate all files.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateFiles() {
    \Drupal::logger('static_generator')->notice('Begin generateFiles()');
    $elapsed_time = $this->generateCodeFiles();
    $elapsed_time += $this->generatePublicFiles();
    \Drupal::logger('static_generator')
      ->notice('End generateFiles(), elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Generate public files.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generatePublicFiles() {
    $start_time = time();

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
    file_unmanaged_save_data($exclude_files, $this->generatorDirectory() . '/exclude_files.txt', FILE_EXISTS_REPLACE);

    // Create files directory if it does not exist.
    $public_files_directory = $this->fileSystem->realpath('public://');
    $generator_directory = $this->generatorDirectory(TRUE);
    exec('mkdir -p ' . $generator_directory . '/sites/default/files');

    // rSync
    $rsync_public = $this->configFactory->get('static_generator.settings')
      ->get('rsync_public');
    $public_files = 'rsync -zr --delete --progress --delete-excluded ' . $rsync_public . ' --exclude-from "' . $generator_directory . '/exclude_files.txt" ' . $public_files_directory . ' ' . $generator_directory . '/sites/default';
    exec($public_files);

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generatePublicFiles() elapsed time: ' . $elapsed_time . ' seconds.');
    }
    return $elapsed_time;
  }

  /**
   * Generate files for core, modules, and themes.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateCodeFiles() {
    $start_time = time();

    $rsync_code = $this->configFactory->get('static_generator.settings')
      ->get('rsync_code');
    $generator_directory = $this->generatorDirectory(TRUE);

    // rSync core.
    $core_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/core ' . $generator_directory;
    exec($core_files);

    // rSync modules.
    $module_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/modules ' . $generator_directory;
    exec($module_files);

    // rSync themes.
    $theme_files = 'rsync -zarv --delete ' . $rsync_code . ' ' . DRUPAL_ROOT . '/themes ' . $generator_directory;
    exec($theme_files);

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateCodeFiles() elapsed time: ' . $elapsed_time . ' seconds.');
    }
    return $elapsed_time;
  }

  /**
   * Generate redirects - requires redirect module.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateRedirects() {
    $start_time = time();

    if (\Drupal::moduleHandler()->moduleExists('redirect')) {
      $storage = $this->entityTypeManager->getStorage('redirect');
      $ids = $storage->getQuery()
        ->execute();
      $redirects = $storage->loadMultiple($ids);
      foreach ($redirects as $redirect) {
        $source_url = $redirect->getSourceUrl();
        $target_array = $redirect->getRedirect();
        $target_uri = $target_array['uri'];
        $target_url = substr($target_uri, 9);
        $this->generateRedirect($source_url, $target_url);
      }
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    return $elapsed_time;
  }

  /**
   * Generate a redirect page file.
   *
   * @param string $source_url
   *   The source url.
   * @param string $target_url
   *   The target url.
   *
   * @throws \Exception
   *
   */
  public function generateRedirect($source_url, $target_url) {
    if (empty($source_url) || empty($target_url)) {
      return;
    }

    // Get the redirect markup.
    $redirect_markup = '<html><head><meta http-equiv="refresh" content="0;URL=' . $target_url . '"></head><body><a href="' . $target_url . '">Page has moved to this location.</a></body></html>';

    // Write redirect page files.
    $web_directory = $this->directoryFromPath($source_url);
    $file_name = $this->filenameFromPath($source_url);
    $directory = $this->generatorDirectory() . $web_directory;
    if (file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($redirect_markup, $directory . '/' . $file_name, FILE_EXISTS_REPLACE);
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
    $path_alias = \Drupal::service('path.alias_manager')
      ->getAliasByPath($path);
    $front = $this->configFactory->get('system.site')->get('page.front');
    $front_alias = \Drupal::service('path.alias_manager')
      ->getAliasByPath($front);
    if ($path_alias == $front_alias) {
      $file_name = 'index.html';
    }
    else {
      $file_name = strrchr($path_alias, '/') . '.html';
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
   * Delete a single page.
   *
   * @param String $path
   *   The page's path.
   *
   * @throws \Exception
   */
  public function deletePage($path) {
    $web_directory = $this->directoryFromPath($path);
    $file_name = $this->filenameFromPath($path);
    file_unmanaged_delete($this->generatorDirectory() . $web_directory . '/' . $file_name);
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

    // Find all of the blocks in the markup.
    $dom = new DomDocument();
    @$dom->loadHTML($markup);
    $finder = new DomXPath($dom);
    $blocks = $finder->query("//div[contains(@class, 'block')]");

    // Get list of blocks to ESI and not to ESI.
    $blocks_esi = $this->configFactory->get('static_generator.settings')
      ->get('blocks_esi');
    $blocks_esi = explode(',', $blocks_esi);
    $blocks_no_esi = $this->configFactory->get('static_generator.settings')
      ->get('blocks_no_esi');
    $blocks_no_esi = explode(',', $blocks_no_esi);

    // Replace each block with ESI if it is on the list of ESI blocks, or if
    // ESI block list is empty. Skip any blocks on the "no ESI" list.
    foreach ($blocks as $block) {

      // Construct block id.
      $id = $block->getAttribute('id');
      if ($id == '') {
        continue;
      }
      $block_id = str_replace('-', '_', substr($id, 6));
      if (substr($block_id, 0, 12) == 'views_block_') {
        //str_replace('views_block_', 'views_block__', $block_id);
        $block_id = 'views_block__' . substr($block_id, 12);
      }

      // Replace block if ESI blocks is empty or if block id is in ESI blocks.
      if (!empty($blocks_esi) && !in_array($block_id, $blocks_esi)) {
        continue;
      }

      // Do not replace block if listed in "no ESI blocks".
      if (in_array($block_id, $blocks_no_esi)) {
        continue;
      }

      // Conditionally generate block.
      if ($generate_blocks) {
        $this->generateBlock($block_id);
      }

      // Create the ESI and then replace the block with the ESI.
      $esi_markup = '<!--#include virtual="/esi/block/' . Html::escape($block_id) . '" -->';
      $esi = $dom->createElement('span', $esi_markup);
      $block->parentNode->replaceChild($esi, $block);

    }

    // Return markup with ESI's.
    $markup_esi = $dom->saveHTML();
    $markup_esi = str_replace('&lt;', '<', $markup_esi);
    $markup_esi = str_replace('&gt;', '>', $markup_esi);

    return $markup_esi;
  }

  /**
   * Get verbose logging setting.
   *
   * @return boolean;
   */
  public function verboseLogging() {
    $verbose_logging = $this->configFactory->get('static_generator.settings')
      ->get('verbose_logging');
    return $verbose_logging;
  }

  /**
   * Get generator directory.
   *
   * @param bool $real_path
   *   Get the real path.
   *
   * @return string
   */
  public function generatorDirectory($real_path = FALSE) {
    $generator_directory = $this->configFactory->get('static_generator.settings')
      ->get('generator_directory');
    if ($real_path) {
      $generator_directory = $this->fileSystem->realpath($generator_directory);
    }
    return $generator_directory;
  }

  /**
   * Delete all generated pages and files.  This is done by deleting the top
   * level directory and then re-creating it.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function deleteAll() {
    $start_time = time();

    $generator_directory = $this->generatorDirectory(TRUE);
    file_unmanaged_delete_recursive($generator_directory, $callback = NULL);
    file_prepare_directory($generator_directory, FILE_CREATE_DIRECTORY);

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('deleteAll() elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Delete all generated pages.  Deletes all generated *.html files,
   * and ESI include files.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function deletePages() {
    $start_time = time();
    $generator_directory = $this->generatorDirectory(TRUE);

    // Delete .html files
    //    $files = file_scan_directory($generator_directory, '(.*?)\.(html)$', ['recurse' => FALSE]);
    //    foreach ($files as $file) {
    //      file_unmanaged_delete_recursive($file, $callback = NULL);
    //    }

    $files = file_scan_directory($generator_directory, '/.*/', ['recurse' => FALSE]);
    foreach ($files as $file) {
      $filename = $file->filename;
      $html_file = substr($filename, -strlen('html')) == 'html';
      if ($html_file) {
        file_unmanaged_delete_recursive($file->uri, $callback = NULL);
      }
      else {
        if (!in_array($filename, ['core', 'modules', 'themes', 'sites'])) {
          file_unmanaged_delete_recursive($file->uri, $callback = NULL);
        }
      }
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('deletePages() elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
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

// Remove admin menu.
//      $dom->validateOnParse = FALSE;
//      $xp = new DOMXPath($dom);
//      $col = $xp->query('//div[ @id="toolbar-administration" ]');
//      if (!empty($col)) {
//        foreach ($col as $node) {
//          $node->parentNode->removeChild($node);
//        }
//      }
