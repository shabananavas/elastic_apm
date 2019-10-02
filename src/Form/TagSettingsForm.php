<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tag settings form for Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class TagSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_tag_pattern';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['elastic_apm.tags'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elastic_apm.tags');

    $form['path_pattern'] = [
      '#type' => 'details',
      '#title' => $this->t('Path pattern settings'),
      '#open' => TRUE,
    ];
    $form['path_pattern']['path_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Pattern Tags'),
      '#default_value' => $config->get('path_pattern'),
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.tags')
      ->set('path_pattern', $values['path_pattern_tags'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}
