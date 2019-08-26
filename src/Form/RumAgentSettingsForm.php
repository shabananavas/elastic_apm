<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Real user monitoring settings form for the Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class RumAgentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_rum_agent_settings';
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

    $form['service_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service name'),
      '#default_value' => $config->get('rumAgent.serviceName'),
    ];

    $form['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL'),
      '#default_value' => $config->get('rumAgent.serverUrl'),
      '#description' => $this->t('APM server endpoint'),
      '#required' => TRUE,
    ];

    $form['service_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service Version'),
      '#default_value' => $config->get('rumAgent.serviceVersion'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('rumAgent.status'),
      '#description' => $this->t('Activate Real user monitoring'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set('rumAgent.serviceName', $values['service_name'])
      ->set('rumAgent.serviceVersion', $values['service_version'])
      ->set('rumAgent.serverUrl', $values['server_url'])
      ->set('rumAgent.status', $values['status'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
