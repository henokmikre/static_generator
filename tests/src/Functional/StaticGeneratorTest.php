<?php

namespace Drupal\Tests\static_generator\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\Routing\Route;

/**
 * Verifies operation of the Static Generator service.
 *
 * @group static_generator
 */
class StaticGeneratorTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'field_ui',
    'filter',
    'text',
    'datetime',
    'options',
    'static_generator',
  ];

  /**
   * Installation profile.
   *
   * @var string
   */
  protected $profile = 'standard';

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
  ];

  /**
   * {@inheritdoc}
   */
  public function setup() {
    parent::setup();
  }

  /**
   * Tests static generator cache of route.
   *
   * @param Route $route
   * The route to cache.
   */
  public function testCacheRoute() {
    $this->assertTrue(TRUE);
  }

  /**
   * Tests clearing the static generation cache.
   */
  public function testCacheClear() {
    $this->assertTrue(TRUE);
  }

  /**
   * Tests generating a single route.
   */
  public function testGenerate() {
    //$response = \Drupal::service('static_generator')->generateRoute('/node/1');
    $this->assertTrue(TRUE);
  }

  /**
   * Tests full generation.
   */
  public function testGenerateAll() {
    $this->assertTrue(TRUE);
  }

}
