<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tag settings form for Elastic APM.
 *
 * Contains path pattern settings and route pattern settings elements.
 *
 * @package Drupal\elastic_apm\Form
 */
class TagSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_tag_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['elastic_apm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elastic_apm.settings');

    $form['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tag settings'),
      '#open' => TRUE,
    ];
    $form['tags']['path_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Pattern Tags'),
      '#default_value' => $config->get('tags')['path_patterns'],
      '#description' => $this->t('
        Configure which tags to send for which pages. Please use : to separate
        the path pattern from the tag and | to separate the key from the value.
        The "*" character is a wildcard. Enter one path per line
        Example:
        <pre>
          /product/*: provider|commerce
          /checkout/*: provider|commerce
        </pre>
      '),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set('tags', [
        'path_patterns' => $values['path_pattern_tags'],
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
