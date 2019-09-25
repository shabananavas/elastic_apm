<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * PHP Agent settings form for the Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class PhpAgentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_php_agent_settings';
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

    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection settings'),
      '#open' => TRUE,
    ];

    $form['connection']['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application name'),
      '#default_value' => $config->get('phpAgent.appName'),
      '#required' => TRUE,
    ];

    $form['connection']['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL'),
      '#default_value' => $config->get('phpAgent.serverUrl'),
      '#description' => $this->t('APM server endpoint'),
      '#required' => TRUE,
    ];

    $form['connection']['secret_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret token'),
      '#default_value' => $config->get('phpAgent.secretToken'),
      '#description' => $this->t('Secret token for APM server'),
      '#required' => TRUE,
    ];

    $form['connection']['host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host name'),
      '#default_value' => $config->get('phpAgent.hostname'),
      '#description' => $this->t('Hostname to transmit to the APM server'),
    ];

    $form['application'] = [
      '#type' => 'details',
      '#title' => $this->t('Application settings'),
    ];

    $form['application']['app_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application version'),
      '#default_value' => $config->get('phpAgent.appVersion'),
    ];

    $form['application']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Guzzle client timeout'),
      '#default_value' => $config->get('phpAgent.timeout'),
    ];

    $form['application']['env'] = [
      '#type' => 'textarea',
      '#title' => $this->t('$_SERVER variables'),
      '#default_value' => !empty($config->get('phpAgent.env')) ? implode(PHP_EOL, $config->get('phpAgent.env')) : '',
      '#description' => $this->t('$_SERVER variables to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application']['cookies'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cookies'),
      '#default_value' => !empty($config->get('phpAgent.cookies')) ? implode(PHP_EOL, $config->get('phpAgent.cookies')) : '',
      '#description' => $this->t('Cookies to send to the APM server, empty set sends all. Keys are case sensitive. <strong>Enter one per line.</strong>'),
    ];

    $form['application']['http_client'] = [
      '#type' => 'details',
      '#title' => $this->t('HTTP Client'),
    ];

    $form['application']['http_client']['verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify'),
      '#default_value' => $config->get('phpAgent.httpClient.verify'),
    ];

    $form['application']['http_client']['proxy'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Proxy'),
      '#default_value' => $config->get('phpAgent.httpClient.proxy'),
    ];

    $form['errors'] = [
      '#type' => 'details',
      '#title' => $this->t('Error settings'),
    ];

    $form['errors']['capture_throwable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture errors and exceptions'),
      '#default_value' => $config->get('phpAgent.captureThrowable'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('phpAgent.status'),
      '#description' => $this->t('Activate the PHP APM agent'),
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

    $this->config('elastic_apm.settings')
      ->set('phpAgent.appName', $values['app_name'])
      ->set('phpAgent.appVersion', $values['app_version'])
      ->set('phpAgent.serverUrl', $values['server_url'])
      ->set('phpAgent.secretToken', $values['secret_token'])
      ->set('phpAgent.hostname', $values['host_name'])
      ->set('phpAgent.timeout', $values['timeout'])
      ->set('phpAgent.env', array_filter($environment_variables))
      ->set('phpAgent.cookies', array_filter($cookies))
      ->set('phpAgent.captureThrowable', $values['capture_throwable'])
      ->set('phpAgent.httpClient', [
        'verify' => $values['verify'],
        'proxy' => $values['proxy'],
      ])
      ->set('phpAgent.status', $values['status'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
