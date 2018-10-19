<?php

namespace Drupal\static_generator;

use DOMXPath;
use DOMDocument;
use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
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
use Drupal\Core\Path\PathMatcherInterface;


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
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

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
   *   File system.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   Path matcher.
   */
  public function __construct(RendererInterface $renderer, RouteMatchInterface $route_match, ClassResolverInterface $class_resolver, RequestStack $request_stack, HttpKernelInterface $http_kernel, ThemeManagerInterface $theme_manager, ThemeInitializationInterface $theme_initialization, ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager, PathMatcherInterface $path_matcher) {
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
    $this->pathMatcher = $path_matcher;
  }

  /**
   * Generate all pages and files.
   *
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
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generatePages() {
    $elapsed_time = $this->deletePages();
    $elapsed_time += $this->deleteBlocks();
    $elapsed_time += $this->generateNodes();
    $elapsed_time += $this->generatePaths();
    $elapsed_time += $this->generateRedirects();
    \Drupal::logger('static_generator')
      ->notice('Generation of all pages complete, elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Generate nodes.
   *
   * @param bool $blocks_only
   * @param bool $blocks_over_write
   * @param string $type
   * @param int $start
   * @param int $length
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function generateNodes($blocks_only = FALSE, $blocks_over_write = FALSE, $type = '', $start = 0, $length = 5000) {
    $elapsed_time_total = 0;

    // Get bundles to generate from config if not specified in $type.
    if (empty($type)) {
      $gen_node_bundles_string = $this->configFactory->get('static_generator.settings')
        ->get('gen_node');
      $gen_node_bundles = explode(',', $gen_node_bundles_string);
    }
    else {
      $gen_node_bundles = [$type];
    }

    // Generate as Anonymous user.
    \Drupal::service('account_switcher')
      ->switchTo(new AnonymousUserSession());

    // Switch to default theme
    $active_theme = $this->themeManager->getActiveTheme();
    $default_theme_name = $this->configFactory->get('system.theme')
      ->get('default');
    $default_theme = $this->themeInitialization->getActiveThemeByName($default_theme_name);
    $this->themeManager->setActiveTheme($default_theme);

    // Generate each bundle.
    foreach ($gen_node_bundles as $bundle) {
      $start_time = time();

      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      $count = $query->count()->execute();

      $count_gen = 0;

      for ($i = $start; $i <= $count; $i = $i + $length) {

        // Reset memory
        //        drupal_static_reset();
        //        $manager = \Drupal::entityManager();
        //        foreach ($manager->getDefinitions() as $id => $definition) {
        //          $manager->getStorage($id)->resetCache();
        //        }
        // Run garbage collector to further reduce memory.
        //        gc_collect_cycles();
        // @TODO Can we reset container?

        $query = \Drupal::entityQuery('node');
        $query->condition('status', 1);
        $query->condition('type', $bundle);
        $query->range($i, $length);
        $entity_ids = $query->execute();

        // Generate pages for bundle.
        foreach ($entity_ids as $entity_id) {
          $path_alias = \Drupal::service('path.alias_manager')
            ->getAliasByPath('/node/' . $entity_id);
          $this->generatePage($path_alias, $blocks_only, FALSE, FALSE, FALSE, FALSE);
          $count_gen++;
        }

        // Exit if single run for specified content type.
        if (!empty($type)) {
          break;
        }
      }

      // Elapsed time.
      $end_time = time();
      $elapsed_time = $end_time - $start_time;
      $elapsed_time_total += $elapsed_time;
      if ($count_gen > 0) {
        $seconds_per_page = round($elapsed_time / $count_gen, 2);
      }
      else {
        $seconds_per_page = 'n/a';
      }

      \Drupal::logger('static_generator')
        ->notice('Gen bundle ' . $bundle . ' ' . $count_gen .
          ' pages in ' . $elapsed_time . ' seconds, ' . $seconds_per_page . ' seconds per page.');
    }

    // Switch back from anonymous user.
    \Drupal::service('account_switcher')->switchBack();

    // Switch back to active theme.
    $this->themeManager->setActiveTheme($active_theme);

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
   * @param string $path
   *   The page's path.
   *
   * @param bool $blocks_only
   *   Optionally omit generating the page (just generate the blocks).
   *
   * @param bool $blocks_over_write
   *   Generate the block fragments referenced by the ESI's even if a
   *   fragment already exists.
   *
   * @param bool $log
   *   Should a log message be written to dblog.
   *
   * @param bool $account_switcher
   *
   * @param bool $theme_switcher
   *
   * @return string|void
   * @throws \Drupal\Core\Theme\MissingThemeDependencyException
   */
  public function generatePage($path, $blocks_only = FALSE, $blocks_over_write = FALSE, $log = FALSE, $account_switcher = TRUE, $theme_switcher = TRUE) {

    // Get path alias for path.
    $path_alias = \Drupal::service('path.alias_manager')
      ->getAliasByPath($path);

    // Return if path is excluded.
    if ($this->excludePath($path)) {
      return;
    }

    // Get/Process markup.
    $markup = $this->markupForPage($path_alias, $account_switcher, $theme_switcher);
    $markup = $this->injectESIs($markup, $blocks_over_write, $path);

    // Write page files.
    $web_directory = $this->directoryFromPath($path_alias);
    $file_name = $this->filenameFromPath($path_alias);

    // Return if on index.html and gen index is false.
    if ($file_name == "index.html" && !$this->generateIndex()) {
      return;
    }

    $directory = $this->generatorDirectory() . $web_directory;
    if (!$blocks_only && file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      file_unmanaged_save_data($markup, $directory . '/' . $file_name, FILE_EXISTS_REPLACE);
      if ($log) {
        \Drupal::logger('static_generator_pages')
          ->notice('Generate Page: ' . $directory . '/' . $file_name);
      }
      return 'done';
    }
  }

  /**
   * Should a path be excluded by "Paths to not generate setting.
   *
   * @param $path
   *
   * @return boolean
   *   Return true if path is excluded, false otherwise.
   *`
   */
  public function excludePath($path) {

    $path_alias = \Drupal::service('path.alias_manager')
      ->getAliasByPath($path);

    // Get paths to exclude (not generate)
    $paths_do_not_generate_string = $this->configFactory->get('static_generator.settings')
      ->get('paths_do_not_generate');
    if (empty($paths_do_not_generate_string)) {
      return FALSE;
    }
    $paths_do_not_generate = explode(',', $paths_do_not_generate_string);

    foreach ($paths_do_not_generate as $path_dng) {
      if ($this->pathMatcher->matchPath($path_alias, $path_dng)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Generate block ESi fragment files.
   *
   * @param bool $frequent_only
   *   Generate frequent blocks only.  Frequent blocks are defined in settings.
   *
   * @return int
   *   Execution time in seconds.
   *`
   * @throws \Exception
   */
  public function generateBlocks($frequent_only = FALSE) {

    if ($frequent_only) {
      // Generate frequent blocks only.
      $blocks_frequent = $this->configFactory->get('static_generator.settings')
        ->get('blocks_frequent');
      if (!empty($blocks_frequent)) {
        $blocks_frequent = explode(',', $blocks_frequent);
        foreach ($blocks_frequent as $block_id) {
          $this->generateBlockById($block_id);
        }
      }
    }
    else {
      // Generate all blocks.
      $this->deleteBlocks();
      return $this->generateNodes(TRUE, FALSE);
    }
  }

  /**
   * Get all Block ID's to ESI, or optionally only those that match a patten.
   *
   * @param string $pattern
   *   The block id pattern.
   *
   * @return array|int
   *
   * @throws \Exception
   */
  public function blockIds($pattern = '') {

    $controller = $this->entityTypeManager->getStorage('block');
    $ids = [];
    foreach ($controller->loadMultiple() as $return_block) {
      $ids[] = $return_block->id();
      //if ($return_block_weight = $return_block->getWeight()) {
      //$this->assertTrue($test_blocks[$id]['weight'] == $return_block_weight, 'Block weight is set as "' . $return_block_weight . '" for ' . $id . ' block.');
      //$position[$id] = strpos($test_content, Html::getClass('block-' . $test_blocks[$id]['id']));
      //}
    }

    //$storage = $this->entityTypeManager->getStorage('block');
    //$ids = $storage->getQuery()
    //  ->execute();
    if (!empty($pattern)) {
      $ids_match_pattern = [];
      foreach ($ids as $id) {
        if (substr($id, 0, strlen($pattern)) === $pattern) {
          $ids_match_pattern[] = $id;
        }
      }
      return $ids_match_pattern;
    }
    else {
      return 'done';
    }
  }

  /**
   * Generate a block fragment file using the block_id and DOM block element.
   *
   * @param $esi_filename
   *   The filename for the generated ESI file.
   *
   * @param \DOMElement $block
   *   The DOM block element.
   *
   * @param $blocks_over_write
   *   Should a block fragment be generated if one already exists.
   *
   */
  public function generateBlockByElement($esi_filename, $block, $blocks_over_write = FALSE) {

    // Return if block fragment already exists and not over writing.
    $dir = $this->generatorDirectory() . '/esi/block';
    file_prepare_directory($dir, FILE_CREATE_DIRECTORY);
    if (!$blocks_over_write) {
      if (file_scan_directory($dir, '/^' . $esi_filename . '$/')) {
        return;
      }
    }

    // Generate block fragment.
    $block_markup = $block->ownerDocument->saveHTML($block);
    file_unmanaged_save_data($block_markup, $dir . '/' . $esi_filename, FILE_EXISTS_REPLACE);
  }

  public function esiBlock($block_id) {

    // Return if block on "no esi" in settings.
    $blocks_no_esi = $this->configFactory->get('static_generator.settings')
      ->get('blocks_no_esi');
    if (empty($blocks_no_esi)) {
      return TRUE;
    }
    $blocks_no_esi = explode(',', $blocks_no_esi);
    if (in_array($block_id, $blocks_no_esi)) {
      return FALSE;
    }

    // Return if block's pattern on "no esi" in settings.
    foreach ($blocks_no_esi as $block_no_esi) {
      if (substr($block_no_esi, strlen($block_no_esi) - 1, 1) === '*') {
        $block_no_esi = substr($block_no_esi, 0, strlen($block_no_esi) - 1);
        if (strpos($block_id, $block_no_esi) === 0) {
          return FALSE;
        }
      }
    }

    // Did not match id or pattern
    return TRUE;
  }

  /**
   * Generate a block fragment file.  This approach generates a block directly,
   * rather than taking the rendered block markup from the rendered pages, which
   * is the approach used when generating all pages.
   *
   * @param string $block_id
   *   The block id.
   *
   * @throws \Exception
   */
  public function generateBlockById($block_id) {
    if (empty($block_id)) {
      return;
    }
    // Return if block id listed in "block no esi" setting.
    if (!$this->esiBlock($block_id)) {
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

    // Unpublished files to exclude.
    $exclude_media_ids = $this->excludeMediaIdsUnpublished();
    if(!isset($exclude_media_ids) || empty($exclude_media_ids)) {
      $exclude_media_ids = [];
    }

    $exclude_files = '';
    foreach ($exclude_media_ids as $exclude_media_id) {
      
      // Get the media entity.
      $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($exclude_media_id);

      // Get the file id.
      $fid = 0;
      if($media->hasField('field_media_image')) {
        $fid = $media->get('field_media_image')->getValue()[0]['target_id'];
      }
      elseif($media->hasField('field_media_file')) {
        $fid = $media->get('field_media_file')->getValue()[0]['target_id'];
      }
      elseif($media->hasField('field_media_audio_file')) {
        $fid = $media->get('field_media_audio_file')->getValue()[0]['target_id'];
      }
      if($fid > 0) {
        $file = File::load($fid);
        $url = Url::fromUri($file->getFileUri());
        $uri = $url->getUri();
        $exclude_file = substr($uri, 9);
        $exclude_files .= $exclude_file . "\r\n";
      }
    }

    // Files to exclude specified in settings.
    $rsync_public_exclude = $this->configFactory->get('static_generator.settings')
      ->get('rsync_public_exclude');
    if (!empty($rsync_public_exclude)) {
      $rsync_public_exclude_array = explode(',', $rsync_public_exclude);
      foreach ($rsync_public_exclude_array as $rsync_public_exclude_file) {
        $exclude_files .= $rsync_public_exclude_file . "\r\n";
      }
    }

    //$tmp_files_directory = $this->fileSystem->realpath('tmp://');
    $public_files_directory = $this->fileSystem->realpath('public://');

    file_unmanaged_save_data($exclude_files, $public_files_directory . '/rsync_public_exclude.tmp', FILE_EXISTS_REPLACE);

    // Create files directory if it does not exist.
    //$public_files_directory = $this->fileSystem->realpath('public://');
    $generator_directory = $this->generatorDirectory(TRUE);
    exec('mkdir -p ' . $generator_directory . '/sites/default/files');

    // rSync
    $rsync_public = $this->configFactory->get('static_generator.settings')
      ->get('rsync_public');
    $rsync_public = $rsync_public . ' --exclude-from "' . $public_files_directory . '/rsync_public_exclude.tmp" ' . $public_files_directory . '/ ' . $generator_directory . '/sites/default/files';
    exec($rsync_public);

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('Generate Public Files elapsed time: ' . $elapsed_time .
          ' seconds. (' . $rsync_public . ')');
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
    $rsync_core = $rsync_code . ' ' . DRUPAL_ROOT . '/core ' . $generator_directory;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateCodeFiles() Core: ' . $rsync_core);
    }
    exec($rsync_core);

    // rSync modules.
    $rsync_modules = $rsync_code . ' ' . DRUPAL_ROOT . '/modules ' . $generator_directory;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateCodeFiles() Modules: ' . $rsync_modules);
    }
    exec($rsync_modules);

    // rSync themes.
    $rsync_themes = $rsync_code . ' ' . DRUPAL_ROOT . '/themes ' . $generator_directory;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateCodeFiles() Themes: ' . $rsync_themes);
    }
    exec($rsync_themes);

    // rSync libraries.
    $rsync_libraries = $rsync_code . ' ' . DRUPAL_ROOT . '/libraries ' . $generator_directory;
    if ($this->verboseLogging()) {
      \Drupal::logger('static_generator')
        ->notice('generateCodeFiles() Libraries: ' . $rsync_libraries);
    }
    exec($rsync_libraries);

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
        if ($this->verboseLogging()) {
          \Drupal::logger('static_generator')
            ->notice('generateRedirects() source: ' . $source_url . ' target: ' . $target_url);
        }
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
   * @param string $path
   *   The page's path.
   *
   * @return string
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
   * @param string $path
   *   The page's path.
   *
   * @return string
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
   * Returns the rendered markup for a path.
   *
   * @param string $path
   *   The path.
   *
   * @param bool $account_switcher
   *
   * This allows caller to switch accounts once, that way the account
   * is not repeatedly switched, if repeated calls to this function are made.
   *
   * @param bool $theme_switcher
   *
   * This allows caller to switch theme once, that way the theme
   * is not repeatedly switched, if repeated calls to this function are made.
   *
   * @return string
   *   The rendered markup.
   *
   * @throws \Drupal\Core\Theme\MissingThemeDependencyException
   */
  public function markupForPage($path, $account_switcher = TRUE, $theme_switcher = TRUE) {

    // Switch to anonymous use.
    if ($account_switcher) {
      // Generate as Anonymous user.
      \Drupal::service('account_switcher')
        ->switchTo(new AnonymousUserSession());
    }

    // Switch to default theme.
    if ($theme_switcher) {
      $active_theme = $this->themeManager->getActiveTheme();
      $default_theme_name = $this->configFactory->get('system.theme')
        ->get('default');
      $default_theme = $this->themeInitialization->getActiveThemeByName($default_theme_name);
      $this->themeManager->setActiveTheme($default_theme);
    }

    //create($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
//      $server = array_replace(array(
//        'SERVER_NAME' => 'localhost',
//        'SERVER_PORT' => 80,
//        'HTTP_HOST' => 'localhost',
//

    // Make internal request.
    $configuration = \Drupal::service('config.factory')
      ->get('static_generator.settings');
    $static_url = $configuration->get('static_url');
    $request = Request::create($path, 'GET',[],[],[],['SERVER_NAME' => $static_url]);
    //$request->server->set('SCRIPT_NAME', $GLOBALS['base_path'] . 'index.php');
    //$request->server->set('SCRIPT_FILENAME', 'index.php');

    // Get the markup from the response.
    $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, FALSE);
    $markup = $response->getContent();

    // Switch back to active theme.
    if ($theme_switcher) {
      $this->themeManager->setActiveTheme($active_theme);
    }

    // Switch back from anonymous user.
    if ($account_switcher) {
      \Drupal::service('account_switcher')->switchBack();
    }

    // Return markup.
    return $markup;

  }

  /**
   * Inject ESI markup for every block.
   *
   * @param string $markup
   *   The markup.
   *
   * @param bool $blocks_over_write
   *   Over write block fragments if they exist.
   *
   * @return string
   *   Markup with ESI's injected.
   *
   * @throws \Exception
   */
  public function injectESIs($markup, $blocks_over_write = FALSE, $path = '') {

    // Find all of the blocks in the markup.
    $dom = new DomDocument();
    @$dom->loadHTML($markup);
    $finder = new DomXPath($dom);
    $blocks = $finder->query("//*[contains(@class, 'block')]");

    // Get list of blocks to ESI.
    //    $blocks_esi = $this->configFactory->get('static_generator.settings')
    //      ->get('blocks_esi');
    //    if (!empty($blocks_esi)) {
    //      $blocks_esi = explode(',', $blocks_esi);
    //    }

    foreach ($blocks as $block) {

      // Construct block id.
      $block_id = $block->getAttribute('id');
      if ($block_id == '') {
        continue;
      }
      if (substr($block_id, 0, 6) == 'block-') {
        $block_id = substr($block_id, 6);
      }
      $block_id = str_replace('-', '_', $block_id);

      // Return if block id listed in "block no esi" setting.
      if (!$this->esiBlock($block_id)) {
        continue;
      }

      // Get ESI filename.
      if(strpos($block_id,'__') > 0){
        $block_id = substr($block_id, 0, strpos($block_id,'__'));
        $path_str = str_replace('/', '-', $path);
        $esi_filename = $block_id . '-' . $path_str;
      } else {
        $esi_filename = $block_id;
      }

      // @TODO Special handling for Views Blocks
      //      if (substr($block_id, 0, 12) == 'views_block_') {
      //        //str_replace('views_block_', 'views_block__', $block_id);
      //        $block_id = 'views_block__' . substr($block_id, 12);
      //      }

      // Create the ESI and then replace the block with the ESI markup.
      $esi_markup = '<!--#include virtual="/esi/block/' . Html::escape($esi_filename) . '" -->';
      $esi = $dom->createElement('span', $esi_markup);
      $block->parentNode->replaceChild($esi, $block);

      // Generate the ESI fragment file.
      $this->generateBlockByElement($esi_filename, $block, $blocks_over_write);

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
   * Get generate index.html setting.
   *
   * @return boolean;
   */
  public function generateIndex() {
    $generate_index = $this->configFactory->get('static_generator.settings')
      ->get('generate_index');
    return $generate_index;
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
    $elapsed_time = $this->deletePages();
    $elapsed_time += $this->deleteBlocks();
    $elapsed_time += $this->deleteDrupal();

    // Elapsed time.
    \Drupal::logger('static_generator')
      ->notice('Delete all elapsed time: ' . $elapsed_time . ' seconds.');
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

    // Get Drupal dirs setting.
    $drupal = $this->configFactory->get('static_generator.settings')
      ->get('drupal');
    if (!empty($drupal)) {
      $drupal_array = explode(',', $drupal);
      $drupal_array[] = 'esi';
    }
    else {
      $drupal_array = ['esi'];
    }

    // Get Non Drupal dirs setting.
    $non_drupal = $this->configFactory->get('static_generator.settings')
      ->get('non_drupal');
    $non_drupal_array = [];
    if (!empty($non_drupal)) {
      $non_drupal_array = explode(',', $non_drupal);
    }

    $files = file_scan_directory($generator_directory, '/.*/', ['recurse' => FALSE]);
    foreach ($files as $file) {
      $filename = $file->filename;
      $html_file = substr($filename, -strlen('html')) == 'html';
      if ($html_file && !in_array($filename, $non_drupal_array)) {
        file_unmanaged_delete_recursive($file->uri, $callback = NULL);
      }
      else {
        if (!in_array($filename, $drupal_array) && !in_array($filename, $non_drupal_array)) {
          if ($filename == 'node') {
            $node_files = file_scan_directory($generator_directory . '/node', '/.*/', ['recurse' => TRUE]);
            foreach ($node_files as $node_file) {
              file_unmanaged_delete_recursive($node_file->uri, $callback = NULL);
            }
            file_unmanaged_delete_recursive($file->uri, $callback = NULL);
          }
          else {
            file_unmanaged_delete_recursive($file->uri, $callback = NULL);
            exec('rm -rf ' . $file->uri);
          }
        }
      }
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('Delete Page elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }


  /**
   * Deletes all generated block include files in /esi/blocks.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function deleteBlocks() {
    $start_time = time();
    $dir = $this->generatorDirectory(TRUE) . '/esi/block';

    // Delete block esi include files and the block directory.
    $block_esi_files = file_scan_directory($dir, '/.*/', ['recurse' => TRUE]);
    foreach ($block_esi_files as $block_esi_file) {
      file_unmanaged_delete_recursive($block_esi_file->uri, $callback = NULL);
    }
    file_unmanaged_delete_recursive($dir, $callback = NULL);

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('Delete blocks elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Delete a single page.
   *
   * @param string $path
   *   The page's path.
   *
   * @throws \Exception
   */
  public function deletePage($path) {
    $web_directory = $this->directoryFromPath($path);
    $file_name = $this->filenameFromPath($path);
    $full_file_name = $this->generatorDirectory() . $web_directory . '/' . $file_name;
    file_unmanaged_delete($full_file_name);
    \Drupal::logger('static_generator')
      ->notice('Deleted page: ' . $full_file_name);
  }

  /**
   * Delete Drupal directories.
   *
   * @return int
   *   Execution time in seconds.
   *
   * @throws \Exception
   */
  public function deleteDrupal() {

    $start_time = time();

    // Get Drupal dirs setting.
    $drupal = $this->configFactory->get('static_generator.settings')
      ->get('drupal');
    $drupal_array = [];
    if (!empty($drupal)) {
      $drupal_array = explode(',', $drupal);
    }

    $generator_directory = $this->generatorDirectory(TRUE);
    $files = file_scan_directory($generator_directory, '/.*/', ['recurse' => FALSE]);
    foreach ($files as $file) {
      $filename = $file->filename;
      if (in_array($filename, $drupal_array)) {
        file_unmanaged_delete_recursive($file->uri, $callback = NULL);
        exec('rm -rf ' . $file->uri);
      }
    }

    // Elapsed time.
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    \Drupal::logger('static_generator')
      ->notice('deleteDrupal() elapsed time: ' . $elapsed_time . ' seconds.');
    return $elapsed_time;
  }

  /**
   * Exclude media that is not published (e.g. Draft or Archived).
   *
   * @throws \Exception
   */
  public function excludeMediaIdsUnpublished() {
    $query = \Drupal::entityQuery('media');
    $query->condition('status', 0);
    $exclude_media_ids = $query->execute();
    return $exclude_media_ids;
  }

  /**
   * List file name and update time for a path.
   *
   * @param $path
   *
   * @return string
   * @throws \Exception
   */
  public function fileInfo($path) {
    $file_name = $this->generatorDirectory(TRUE) . $this->directoryFromPath($path) . '/' .
      $this->filenameFromPath($path);
    if (file_exists($file_name)) {
      $return_string = $file_name . '<br/>' . date("F j, Y, g:i a", filemtime($file_name));
    }
    else {
      $return_string = 'Static page file not found.';
    }
    return $return_string;
  }

  /**
   * Get generation info for a page.
   *
   * @param $path
   *
   * @param array $form
   *
   * @param bool $details
   *
   * @return array
   *
   * @throws \Exception
   */
  public function generationInfoForm($path, &$form = [], $details = FALSE) {

    // Name and date info for static file.
    $file_info = $this->fileInfo($path);

    // Get path alias for path.
    $path_alias = \Drupal::service('path.alias_manager')
      ->getAliasByPath($path);

    // Get static URL setting.
    $configuration = \Drupal::service('config.factory')
      ->get('static_generator.settings');
    $static_url = $configuration->get('static_url');

    $form['static_generator'] = [
      '#title' => t('Static Generation'),
      '#description' => t(''),
      '#group' => 'advanced',
      '#open' => FALSE,
      'markup' => [
        '#markup' => '<br/>' . $file_info . '<br/><br/>' .
          '<a  target="_blank" href="' . $path . '/gen' . '">Generate Static Page</a><br/><br/>' .
          '<a  target="_blank" href="' . $static_url . $path_alias . '">View Static Page</a>',
      ],
      //      'button' =>
      //        [
      //          '#type' => 'button',
      //          '#value' => 'Generate Page',
      //          //'#value' => $this->t('Ajax refresh'),
      //          //'#ajax' => ['callback' => [$this, 'ajaxCallback']],
      //        ],
      '#weight' => 1000,
    ];

    // Create form details.
    if ($details) {
      $form['static_generator']['#type'] = 'details';
    }

    return $form;
  }

  /**
   * @param $path
   *
   * @return \Drupal\Component\Render\MarkupInterface
   * @throws \Exception
   */
  public function generationInfo($path) {
    $form = $this->generationInfoForm($path);
    $markup = $this->renderer->render($form);
    return $markup;
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


/**
 * Creates a block instance based on default settings.
 *
 * @param string $plugin_id
 *   The plugin ID of the block type for this block instance.
 * @param array $settings
 *   (optional) An associative array of settings for the block entity.
 *   Override the defaults by specifying the key and value in the array, for
 *   example:
 *
 * @code
 *     $this->drupalPlaceBlock('system_powered_by_block', array(
 *       'label' => t('Hello, world!'),
 *     ));
 * @endcode
 *   The following defaults are provided:
 *   - label: Random string.
 *   - ID: Random string.
 *   - region: 'sidebar_first'.
 *   - theme: The default theme.
 *   - visibility: Empty array.
 *
 * @return \Drupal\block\Entity\Block
 *   The block entity.
 *
 * @todo
 *   Add support for creating custom block instances.
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
//  public function placeBlock($plugin_id, array $settings = []) {
//    $config = \Drupal::configFactory();
//    $settings += [
//      'plugin' => $plugin_id,
//      'region' => 'sidebar_first',
//      'id' => strtolower(substr(uniqid(), 0, 8)) . time(),
//      'theme' => $config->get('system.theme')->get('default'),
//      //'label' => substr(uniqid(), 0, 8),
//      'label' => 'test label',
//      'visibility' => [],
//      'weight' => 0,
//    ];
//    $values = [];
//    foreach ([
//               'region',
//               'id',
//               'theme',
//               'plugin',
//               'weight',
//               'visibility',
//             ] as $key) {
//      $values[$key] = $settings[$key];
//      // Remove extra values that do not belong in the settings array.
//      unset($settings[$key]);
//    }
//    foreach ($values['visibility'] as $id => $visibility) {
//      $values['visibility'][$id]['id'] = $id;
//    }
//    $values['settings'] = $settings;
//    $block = Block::create($values);
//    //$block->save();
//    return $block;
//  }
