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

    $form['track_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track user'),
      '#description' => $this->t('When checked, the user ID, username and email address of the user initiating the tracked requests will be send to the Elastic APM server. Doing so will make that data available, and make the user identifiable, to anybody having direct access or access via a UI such as Kibana to Elastic APM or other parts of the Elastic Stack.'),
      '#default_value' => $config->get('privacy')['track_user'],
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
        'track_user' => $values['track_user'],
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
