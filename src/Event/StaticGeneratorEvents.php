<?php

namespace Drupal\static_generator\Event;

/**
 * Defines events for Static Generator.
 */
final class StaticGeneratorEvents {

  /**
   * Allow markup modifiers to be fired when creating the main markup.
   *
   * @Event
   *
   * @see \Drupal\static_generator\Event\ModifyMarkupEvent
   *
   * @var string
   */
  const MODIFY_MARKUP = 'static_generator.modify_markup';

  /**
   * Allow esi markup to be fired when creating ESIs.
   *
   * @Event
   *
   * @see \Drupal\static_generator\Event\ModifyEsiMarkupEvent
   *
   * @var string
   */
  const MODIFY_ESI_MARKUP = 'static_generator.modify_esi_markup';
}
