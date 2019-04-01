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

    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection configuration'),
      '#open' => TRUE,
    ];

    $form['connection']['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application name'),
      '#default_value' => $config->get('appName'),
      '#required' => TRUE,
    ];

    $form['connection']['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL'),
      '#default_value' => $config->get('serverUrl'),
      '#description' => $this->t('APM server endpoint'),
      '#required' => TRUE,
    ];

    $form['connection']['secret_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret token'),
      '#default_value' => $config->get('secretToken'),
      '#description' => $this->t('Secret token for APM server'),
      '#required' => TRUE,
    ];

    $form['connection']['host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host name'),
      '#default_value' => $config->get('hostname'),
      '#description' => $this->t('Hostname to transmit to the APM server'),
    ];

    $form['application'] = [
      '#type' => 'details',
      '#title' => $this->t('Application configuration'),
    ];

    $form['application']['app_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application version'),
      '#default_value' => $config->get('appVersion'),
    ];

    $form['application']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Guzzle client timeout'),
      '#default_value' => $config->get('timeout'),
    ];

    $form['application']['apm_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('APM server intake API version'),
      '#description' => $this->t('<strong>Currently only the \'v1\' intake API of the APM server is supported.</strong>'),
      '#default_value' => $config->get('apmVersion'),
      '#required' => TRUE,
    ];

    $form['application']['env'] = [
      '#type' => 'textarea',
      '#title' => $this->t('$_SERVER variables'),
      '#default_value' => !empty($config->get('env')) ? implode(PHP_EOL, $config->get('env')) : '',
      '#description' => $this->t('$_SERVER variables to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application']['cookies'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cookies'),
      '#default_value' => !empty($config->get('cookies')) ? implode(PHP_EOL, $config->get('cookies')) : '',
      '#description' => $this->t('Cookies to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application']['http_client'] = [
      '#type' => 'details',
      '#title' => $this->t('HTTP Client'),
    ];

    $form['application']['http_client']['verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify'),
      '#default_value' => $config->get('httpClient.verify'),
    ];

    $form['application']['http_client']['proxy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Proxy'),
      '#default_value' => $config->get('httpClient.proxy'),
    ];

    $form['errors'] = [
      '#type' => 'details',
      '#title' => $this->t('Error configuration'),
    ];

    $form['errors']['capture_exceptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture errors and exceptions'),
      '#default_value' => $config->get('captureExceptions'),
    ];

    $form['active'] = [
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
      ->set('appName', $values['app_name'])
      ->set('appVersion', $values['app_version'])
      ->set('serverUrl', $values['server_url'])
      ->set('secretToken', $values['secret_token'])
      ->set('hostname', $values['host_name'])
      ->set('timeout', $values['timeout'])
      ->set('apmVersion', $values['apm_version'])
      ->set('env', array_filter($environment_variables))
      ->set('cookies', array_filter($cookies))
      ->set('captureExceptions', $values['capture_exceptions'])
      ->set('httpClient', [
        'verify' => $values['verify'],
        'proxy' => $values['proxy'],
      ])
      ->set('active', $values['active'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
