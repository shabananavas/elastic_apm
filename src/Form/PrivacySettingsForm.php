<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Privacy settings form for the Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class PrivacySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_privacy_settings';
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

    $form['send_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send user details'),
      '#default_value' => $config->get('privacy')['send_user'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set('privacy', [
        'send_user' => $values['send_user'],
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
