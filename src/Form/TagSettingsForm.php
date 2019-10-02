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
    $config = $this->config('elastic_apm.settings')->get('tags');

    $form['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tag settings'),
      '#open' => TRUE,
    ];
    $form['tags']['path_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Pattern Tags'),
      '#default_value' => $config['path_pattern'],
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
    $form['tags']['route_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Route Pattern Tags'),
      '#default_value' => $config['route_pattern'],
      '#description' => $this->t('
        Configure which tags to send for which pages. Please use : to separate
        the path pattern from the tag and | to separate the key from the value.
        The "*" character is a wildcard. Enter one path per line
        Example:
        <pre>
          entity.commerce_product.canonical: provider|commerce
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
        'route_pattern' => $values['route_pattern_tags'],
        'path_pattern' => $values['path_pattern_tags'],
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
