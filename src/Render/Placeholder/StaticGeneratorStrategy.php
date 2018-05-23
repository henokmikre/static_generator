<?php

namespace Drupal\static_generator\Render\Placeholder;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the StaticGenerator placeholder strategy, to create ESI's.
 *
 */
class StaticGeneratorStrategy implements PlaceholderStrategyInterface {

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new StaticGeneratorStrategy class.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(SessionConfigurationInterface $session_configuration, RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->sessionConfiguration = $session_configuration;
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function processPlaceholders(array $placeholders) {
    //return $placeholders;
    //$request = $this->requestStack->getCurrentRequest();
    return $this->doProcessPlaceholders($placeholders);
  }

  /**
   * Transforms placeholders to StaticGenerator placeholders.
   *
   * @param array $placeholders
   *   The placeholders to process.
   *
   * @return array
   *   The StaticGenerator placeholders.
   */
  protected function doProcessPlaceholders(array $placeholders) {
    $overridden_placeholders = [];
    foreach ($placeholders as $placeholder => $placeholder_elements) {
      // StaticGenerator uses JavaScript and the DOM to find the placeholder to replace.
      // This means finding the placeholder to replace must be efficient. Most
      // placeholders are HTML, which we can find efficiently thanks to the
      // querySelector API. But some placeholders are HTML attribute values or
      // parts thereof, and potentially even plain text in DOM text nodes. For
      // StaticGenerator's JavaScript to find those placeholders, it would need to
      // iterate over all DOM text nodes. This is highly inefficient. Therefore,
      // the StaticGenerator placeholder strategy only converts HTML placeholders into
      // StaticGenerator placeholders. The other placeholders need to be replaced on the
      // server, not via StaticGenerator.
      // @see \Drupal\Core\Access\RouteProcessorCsrf::renderPlaceholderCsrfToken()
      // @see \Drupal\Core\Form\FormBuilder::renderFormTokenPlaceholder()
      // @see \Drupal\Core\Form\FormBuilder::renderPlaceholderFormAction()

      $overridden_placeholders[$placeholder] = static::createStaticGeneratorPlaceholder($placeholder, $placeholder_elements);
    }
    return $overridden_placeholders;
  }

  /**
   * Creates a StaticGenerator placeholder.
   *
   * @param string $original_placeholder
   *   The original placeholder.
   * @param array $placeholder_render_array
   *   The render array for a placeholder.
   *
   * @return array
   *   The resulting StaticGenerator placeholder render array.
   */
  protected static function createStaticGeneratorPlaceholder($original_placeholder, array $placeholder_render_array) {
    $static_generator_placeholder_id = static::generateStaticGeneratorPlaceholderId($original_placeholder, $placeholder_render_array);

    return [
      '#markup' => '<span data-static-generator-placeholder-id="' . Html::escape($static_generator_placeholder_id) . '"></span>',
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Generates a StaticGenerator placeholder ID.
   *
   * @param string $original_placeholder
   *   The original placeholder.
   * @param array $placeholder_render_array
   *   The render array for a placeholder.
   *
   * @return string
   *   The generated StaticGenerator placeholder ID.
   */
  protected static function generateStaticGeneratorPlaceholderId($original_placeholder, array $placeholder_render_array) {
    // Generate a StaticGenerator placeholder ID (to be used by StaticGenerator's ESI's).
    // @see \Drupal\Core\Render\PlaceholderGenerator::createPlaceholder()
    if (isset($placeholder_render_array['#lazy_builder'])) {
      $callback = $placeholder_render_array['#lazy_builder'][0];
      $arguments = $placeholder_render_array['#lazy_builder'][1];
      $token = Crypt::hashBase64(serialize($placeholder_render_array));
      return UrlHelper::buildQuery(['callback' => $callback, 'args' => $arguments, 'token' => $token]);
    }
    // When the placeholder's render array is not using a #lazy_builder,
    // anything could be in there: only #lazy_builder has a strict contract that
    // allows us to create a more sane selector. Therefore, simply the original
    // placeholder into a usable placeholder ID, at the cost of it being obtuse.
    else {
      return Html::getId($original_placeholder);
    }
  }

}
