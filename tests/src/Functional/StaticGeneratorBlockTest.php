<?php

namespace Drupal\Tests\static_generator\Functional;

use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests generating blocks.
 *
 * @group block
 */
class StaticGeneratorBlockTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Installation profile.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'static_generator',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissionsAdmin = [
    'administer static generator',
    'access administration pages',
    'administer users',
    'administer account settings',
    'administer site configuration',
    'administer user fields',
    'administer user form display',
    'administer user display',
    'administer blocks',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->permissionsAdmin, 'test_admin', TRUE);

    $this->container->get('theme_installer')->install(['stable', 'classy']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'classy')->save();
  }

  /**
   * Tests block generation.
   *
   * @throws \Exception
   */
  public function testBlockGeneration() {

    // Create and login as admin user.
    $this->drupalLogin($this->adminUser);

    for ($i = 1; $i < 10; $i++) {
      $block_id = substr(uniqid(), 0, 10);

      // Enable a standard block.
      //$default_theme = $this->config('system.theme')->get('default');
      $edit = [
        'edit-info-0-value' => $block_id,
      ];
      $this->drupalGet('block/add');
      $this->assertSession()->pageTextContains('Add custom block');
      $this->drupalPostForm(NULL, $edit, t('Save'));

    }

    \Drupal::service('static_generator')->generateBlocks();

  }

}
