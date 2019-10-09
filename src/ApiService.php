<?php

namespace Drupal\elastic_apm;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

use PhilKra\Agent;

/**
 * The Elastic APM service object.
 *
 * @package Drupal\elastic_apm
 */
class ApiService implements ApiServiceInterface {

  /**
   * A config array for the elastic_apm configuration.
   *
   * @var array
   */
  protected $config;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Constructs an Elastic APM object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AccountProxyInterface $account
  ) {
    $this->account = $account;

    $this->config = $configFactory->get('elastic_apm.settings')->get();
    $this->config += [
      'framework' => 'Drupal',
      'frameworkVersion' => Drupal::VERSION,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function captureThrowable() {
    return $this->config['phpAgent']['captureThrowable'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPhpAgent(array $options = []) {
    // Add the user info to the options.
    $options['user'] = [
      'id' => $this->account->id(),
      'email' => $this->account->getEmail(),
      'username' => $this->account->getAccountName(),
    ];

    // Initialize and return our PHP Agent.
    return new Agent(
      $this->config['phpAgent'],
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRumAgentScript(array $options = []) {
    // Initialize and return RUM Agent script.
    return 'elasticApm.init({' .
      'serviceName:' . $this->config['rumAgent']['serviceName'] . ',' .
      'serverUrl:' . $this->config['rumAgent']['serverUrl'] . ',' .
      'serviceVersion:' . $this->config['rumAgent']['serviceVersion'] .
    '})';
  }

  /**
   * {@inheritdoc}
   */
  public function isPhpAgentEnabled() {
    return $this->config['phpAgent']['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function isRumAgentEnabled() {
    return $this->config['rumAgent']['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function isPhpAgentConfigured() {
    $is_configured = TRUE;

    $required_settings = [
      'appName',
      'serverUrl',
      'secretToken',
      'apmVersion',
    ];
    foreach ($required_settings as $key) {
      if (empty($this->config['phpAgent'][$key])) {
        $is_configured = FALSE;
        break;
      }
    }

    return $is_configured;
  }

}
