<?php

namespace Drupal\Tests\static_generator\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class to test StaticGenerator features.
 */
abstract class StaticGeneratorTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Core modules.
    // @see testAvailableConfigEntities
    'node',
    // 'views', // Required to load the front page.

    // // Core test modules.
    // 'entity_test',
    // 'test_page_test',

    // This module.
    'static_generator',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setup();

    // Create content types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Set configuration values for the Static Generator module.
    $config = $this->config('static_generator.settings');
    $config->set('guzzle_host', $this->getURL());
    $config->set('static_url', 'http://static-site.local');
    $config->set('gen_node', 'page, article');
    $config->save();

    // Initiate sessions with user with appropriate permissions.
    $permissions = [
      'administer static generator',
      'access administration pages',
      'administer site configuration',
    ];

    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);
  }

}
