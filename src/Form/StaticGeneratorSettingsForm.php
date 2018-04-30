<?php

namespace Drupal\static_generator\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StaticSettingsForm.
 *
 * @package Drupal\static\Form
 *
 * @ingroup static
 */
class StaticGeneratorSettingsForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'static_generator_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'static_generator.settings',
    ];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
//  public function submitForm(array &$form, FormStateInterface $form_state) {
//    /* TODO: Add some sanity checking so they can't just pull random junk */
//    $endpoint_url = $form_state->getValue('endpoint_url');
//    $this->config('static.settings')
//      ->set('endpoint_url', $endpoint_url)
//      ->save();
//  }

  /**
   * Defines the settings form for the Static Generator module.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_generator.settings');
    $form['static_generator_settings']['#markup'] = 'Static Generator Settings';
    $form['production_host_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Production host URL'),
      '#default_value' => $config->get('production_host_url'),
      '#description' => $this->t('The Production host URL for the statically generated site.'),
    ];
    $form['generator_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Generator directory'),
      '#default_value' => $config->get('generator_directory'),
      '#description' => $this->t('The static generator target directory.'),
    ];
    return parent::buildForm($form, $form_state);
  }
}
