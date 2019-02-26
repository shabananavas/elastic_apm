<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for the Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['elastic_apm.configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elastic_apm.configuration');

    $form['application_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Application configuration'),
      '#open' => TRUE,
    ];
    $form['application_configuration']['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application name'),
      '#default_value' => $config->get('appName'),
      '#required' => TRUE,
    ];

    $form['application_configuration']['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL'),
      '#default_value' => $config->get('serverUrl'),
      '#description' => $this->t('APM server endpoint'),
      '#required' => TRUE,
    ];

    $form['application_configuration']['secret_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret token'),
      '#default_value' => $config->get('secretToken'),
      '#description' => $this->t('Secret token for APM server'),
      '#required' => TRUE,
    ];

    $form['application_configuration']['host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host name'),
      '#default_value' => $config->get('hostname'),
      '#description' => $this->t('Hostname to transmit to the APM server'),
    ];

    $form['application_configuration']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced configuration'),
    ];

    $form['application_configuration']['advanced']['app_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application version'),
      '#default_value' => $config->get('appVersion'),
    ];

    $form['application_configuration']['advanced']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Guzzle client timeout'),
      '#default_value' => $config->get('timeout'),
    ];

    $form['application_configuration']['advanced']['apm_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('APM server intake API version'),
      '#default_value' => $config->get('apmVersion'),
    ];

    $form['application_configuration']['advanced']['env'] = [
      '#type' => 'textarea',
      '#title' => $this->t('$_SERVER variables'),
      '#default_value' => implode(PHP_EOL, $config->get('env')),
      '#description' => $this->t('$_SERVER variables to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application_configuration']['advanced']['cookies'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cookies'),
      '#default_value' => implode(PHP_EOL, $config->get('cookies')),
      '#description' => $this->t('Cookies to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application_configuration']['advanced']['http_client'] = [
      '#type' => 'details',
      '#title' => $this->t('HTTP Client'),
    ];

    $form['application_configuration']['advanced']['http_client']['verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify'),
      '#default_value' => $config->get('httpClient.verify'),
    ];

    $form['application_configuration']['advanced']['http_client']['proxy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Proxy'),
      '#default_value' => $config->get('httpClient.proxy'),
    ];

    $form['application_configuration']['capture_exceptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture errors and exceptions'),
      '#default_value' => $config->get('capture_exceptions'),
    ];

    $form['application_configuration']['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('active'),
      '#description' => $this->t('Activate the APM agent'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $environment_variables = preg_split("(\r\n?|\n)", $values['env']);
    $cookies = preg_split("(\r\n?|\n)", $values['cookies']);

    $this->config('elastic_apm.configuration')
      ->set('appName', $form_state->getValue('app_name'))
      ->set('appVersion', $form_state->getValue('app_version'))
      ->set('serverUrl', $form_state->getValue('server_url'))
      ->set('secretToken', $form_state->getValue('secret_token'))
      ->set('hostname', $form_state->getValue('host_name'))
      ->set('timeout', $form_state->getValue('timeout'))
      ->set('apmVersion', $form_state->getValue('apm_version'))
      ->set('env', array_filter($environment_variables))
      ->set('cookies', array_filter($cookies))
      ->set('capture_exceptions', $form_state->getValue('capture_exceptions'))
      ->set('httpClient', [
        'verify' => $values['verify'],
        'proxy' => $values['proxy'],
      ])
      ->set('active', $form_state->getValue('active'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
