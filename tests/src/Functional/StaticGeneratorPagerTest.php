<?php

namespace Drupal\Tests\static_generator\Functional;

use DOMDocument;
use DOMXPath;

/**
 * Tests pager markup transformation.
 *
 * @group static_generator
 */
class StaticGeneratorPagerTest extends StaticGeneratorTestBase {

  /**
   * Tests preservation of external URLs with pagers.
   */
  public function testExternalLinksWithPagers() {
    $session = $this->assertSession();
    $session->statusCodeEquals(200);

    // Create an array of links with title.
    $links = [
      'Drupal modules' => 'https://www.drupal.org/project/project_module?page=1',
      'Drupal modules' => 'https://www.drupal.org/project/project_module?page=2', // Testig duplicate title.
      'Drupal shops' => 'https://www.drupal.org/organizations?page=1',
      'Dummy google URL' => 'https://www.google.com/?page=1',
    ];

    // Create a markup with links.
    $markup = '';
    foreach ($links as $title => $link) {
      $markup .= '<p><a href="' . $link . '">' . $title . '</a></p><br />';
    }

    // Create a test node.
    $node = $this->drupalCreateNode([
      'title' => 'Test',
      'type' => 'article',
      'body' => [
        'value' => $markup,
        'format' => 'full_html',
      ],
    ]);

    // Check that the new values are found in the response.
    $this->drupalGet('node/' . $node->id());
    $session->statusCodeEquals(200);

    // Generate the markup for the page.
    $output = \Drupal::service('static_generator')->markupForPage('/node/' . $node->id());

    // Write the output to a file in the browser output directory (for debugging).
    $browser_output_directory = $this->htmlOutputDirectory;
    file_put_contents($browser_output_directory . '/output.html', $output);

    // Load the output into a DOMDocument.
    $dom = new DOMDocument();
    @$dom->loadHTML($output);
    $finder = new DOMXPath($dom);

    foreach ($links as $title => $link) {
      $generatedHrefs = $finder->query("//*[contains(text(), '" . $title . "')]");
      foreach ($generatedHrefs as $generatedHref) {
        $this->assertEquals($link, $generatedHref->getAttribute('href'));
      }
    }
  }

}
