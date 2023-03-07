<?php

namespace Drupal\Tests\static_generator\Functional;

/**
 * Tests that Static Generator works correctly.
 *
 * @group static_generator
 */
class StaticGeneratorTest extends StaticGeneratorTestBase {

  /**
   * Tests generating markup for a single route.
   */
  public function testGenerateStaticMarkupForRoute() {
    $session = $this->assertSession();
    $session->statusCodeEquals(200);

    // Create a test node.
    $node = $this->drupalCreateNode([
      'title' => 'Hello, world!',
      'type' => 'article',
      'body' => [
        'value' => 'Test body text'
      ],
    ]);

    // Check that the new values are found in the response.
    $this->drupalGet('node/' . $node->id());
    $session->statusCodeEquals(200);

    // Generate the markup for the page.
    $output = \Drupal::service('static_generator')->markupForPage('/node/' . $node->id());

    // Assert that the generated markup contains the expected text.
    $this->assertStringContainsString("Test body text", $output);
  }

}
