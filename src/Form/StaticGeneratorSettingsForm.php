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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Generator directory.
    $generator_directory = $form_state->getValue('generator_directory');
    $this->config('static_generator.settings')
      ->set('generator_directory', $generator_directory)
      ->save();

    // Paths - generate.
    $paths_generate = $form_state->getValue('paths_generate');
    $this->config('static_generator.settings')
      ->set('paths_generate', $paths_generate)
      ->save();

    // Paths - do not generate.
    $paths_do_not_generate = $form_state->getValue('paths_do_not_generate');
    $this->config('static_generator.settings')
      ->set('paths_generate', $paths_do_not_generate)
      ->save();

  }

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

    $form['generator_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Generator directory'),
      '#default_value' => $config->get('generator_directory'),
      '#description' => $this->t('The static generator target directory.'),
    ];

    $form['paths_generate'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths to Generate'),
      '#description' => $this->t('Specify paths to generate - comma separated, no spaces.'),
      '#default_value' => $config->get('paths_generate'),
    ];

    $form['paths_do_not_generate'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths to not Generate'),
      '#description' => $this->t('Specify paths to not generate - comma separated, no spaces.'),
      '#default_value' => $config->get('paths_do_not_generate'),
    ];

    return parent::buildForm($form, $form_state);
  }
}
