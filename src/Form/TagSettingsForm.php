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
    return 'elastic_apm_tag_route_pattern';
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

    $form['route_pattern'] = [
      '#type' => 'details',
      '#title' => $this->t('Route pattern settings'),
      '#open' => TRUE,
    ];
    $form['route_pattern']['route_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Route Pattern Tags'),
      '#default_value' => $config->get('route_pattern'),
      '#description' => $this->t('
        Configure which tags to send for which routes.
        Please add route tag settings in YML format with route pattern as key
        and tag as value. The "*" character is a wildcard. An example route
        is  entity.node.* for every node page.
        Example:
        <pre>
        node.*: node
        entity.node.*: node
        user.*: user
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
      ->set('route_pattern', $values['route_pattern_tags'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}
