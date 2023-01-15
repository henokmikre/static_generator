<?php

namespace Drupal\static_generator\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\static_generator\StaticGenerator;
use Drush\Exceptions\UserAbortException;

/**
 * Static Generator Drush commandfile.
 */
class StaticGeneratorCommands extends DrushCommands {
  /**
   * The Static Generator service.
   *
   * @var \Drupal\static_generator\StaticGenerator
   */
  protected $staticGenerator;

  /**
   * StaticGeneratorCommands constructor.
   *
   * @param \Drupal\static_generator\StaticGenerator $static_generator
   */
  public function __construct(StaticGenerator $staticGenerator) {
    $this->staticGenerator = $staticGenerator;
  }

  /**
   * Delete all generated files.
   *
   * @option pages
   *   Delete pages.
   * @option esi
   *   Delete ESIs.
   *
   * @command static_generator:delete
   * @aliases sgd
   */
  public function delete($options = ['pages' => FALSE, 'esi' => FALSE]) {
    if ($options['pages']) {
      if (!$this->io()->confirm(dt('Delete all pages?'))) {
        throw new UserAbortException();
      } else {
        $elapsed_time = $this->staticGenerator->deletePages();
        $this->output()->writeln('Delete all generated pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
      }
    }
    elseif ($options['esi']) {
      if (!$this->io()->confirm(dt('Delete all pages?'))) {
        throw new UserAbortException();
      } else {
        $elapsed_time = $this->staticGenerator->deleteEsi();
        $this->output()->writeln('Delete all generated ESIs completed, elapsed time: ' . $elapsed_time . ' seconds.');
      }
    } else {
      if (!$this->io()->confirm(dt('Delete all pages?'))) {
        throw new UserAbortException();
      } else {
        $elapsed_time = $this->staticGenerator->deleteAll();
        $this->output()->writeln('Delete all generated ESIs, files and pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
      }
    }
  }

  /**
   * Generate all.
   *
   * @command static_generator:generate-all
   * @aliases sg
   */
  public function generateAll() {
    if (!$this->io()->confirm(dt('Delete and re-generate entire static site?'))) {
      throw new UserAbortException();
    } else {
      $elapsed_time = $this->staticGenerator->generateAll();
      $this->output()->writeln('Full site static generation complete, elapsed time: ' . $elapsed_time . ' seconds.');
    }
  }

  /**
   * Generate blocks.
   *
   * @command static_generator:generate-blocks
   * @aliases sgb
   */
  public function generateBlocks($block_id, $options = ['frequent' => FALSE]) {
    $elapsed_time = 0;

    if (empty($block_id)) {
      if (empty($options['frequent'])) {
        // Generate all blocks.
        if (!$this->io()->confirm(dt('Delete and re-generate all blocks?'))) {
          throw new UserAbortException();
        } else {
          $elapsed_time = $this->staticGenerator->generateBlocks();
        }
      }
      else {
        // Generate frequent blocks.
        $elapsed_time = $this->staticGenerator->generateBlocks(TRUE);
      }

      $this->output()->writeln('Generate blocks completed, elapsed time: ' . $elapsed_time . ' seconds.');
    }
    else {
      // Generate single block.
      $this->staticGenerator->generateBlock($block_id);
      $this->output()->writeln('Generate of block ' . $block_id . ' complete.');
    }
  }

  /**
   * Generate files.
   *
   * @command static_generator:generate-files
   * @aliases sgf
   */
  public function generateFiles($options = ['public' => FALSE, 'code' => FALSE]) {
    $elapsed_time = 0;

    if (empty($options['public']) && empty($options['code'])) {
      $elapsed_time = $this->staticGenerator->generateFiles();
    }
    else {
      if ($options['public']) {
        $elapsed_time = $this->staticGenerator->generatePublicFiles();
      }
      if ($options['code']) {
        $elapsed_time = $this->staticGenerator->generateCodeFiles();
      }
    }

    $this->output()->writeln('Files generation complete, elapsed time: ' . $elapsed_time . ' seconds.');
  }

  /**
   * Generate pages.
   *
   * @command static_generator:generate-pages
   * @aliases sgp
   */
  public function generatePages($path = '', $options = ['queued' => FALSE, 'code' => FALSE, 'q' => FALSE]) {
    if (empty($path)) {
      if (!empty($options['queued'])) {
        $this->staticGenerator->processQueue();
      }
      else {
        if (empty($options['q'])) {
          if (!$this->io()->confirm(dt('Delete and re-generate all pages?'))) {
            throw new UserAbortException();
          } else {
            $elapsed_time = $this->staticGenerator->generatePages(TRUE);
            $this->output()->writeln('Generate pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
          }
        }
        else {
          $elapsed_time = $this->staticGenerator->generatePages();
          $elapsed_time += $this->staticGenerator->generateMedia('remote_video');

          $this->output()->writeln('Generate pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
        }
      }
    }
    else {
      $empty_array = [];
      $this->staticGenerator->generatePage($path, '', FALSE, TRUE, TRUE, TRUE, $empty_array, $empty_array, $empty_array, TRUE);
      $this->output()->writeln('Generation of page for path ' . $path . ' complete.');
    }
  }

  /**
   * Generate page types.
   *
   * @command static_generator:generate-pages-type
   * @aliases sgpt
   */
  public function generatePageType($type, $bundle, $start, $length) {
    $elapsed_time = 0;

    if ($type === 'node') {
      $elapsed_time = $this->staticGenerator->generateNodes($bundle, FALSE, $start, $length);
    }
    elseif ($type === 'media') {
      $elapsed_time = $this->staticGenerator->generateMedia($bundle, FALSE, $start, $length);
    }
    elseif ($type === 'vocabulary') {
      $elapsed_time = $this->staticGenerator->generateVocabulary($bundle, FALSE, $start, $length);
    }
    $this->output()
      ->writeln('Generation of pages for type: ' . $type . ' bundle: '. $bundle . ' complete, elapsed time: ' . $elapsed_time . ' seconds.');
  }

  /**
   * Generate redirects.
   *
   * @command static_generator:generate-redirects
   * @aliases sgr
   */
  public function generateRedirects() {
    $elapsed_time = 0;

    $elapsed_time = $this->staticGenerator->generateRedirects();
    $this->output()->writeln('Generate redirects completed, elapsed time: ' . $elapsed_time . ' seconds.');
  }

}
