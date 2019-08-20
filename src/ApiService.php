<?php

namespace Drupal\elastic_apm;

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
    $this->config = $configFactory->get('elastic_apm.connection_settings')->get();
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function captureThrowable() {
    return $this->config['captureThrowable'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAgent(array $options = []) {
    // Add the user info to the options.
    $options['user'] = [
      'id' => $this->account->id(),
      'email' => $this->account->getEmail(),
      'username' => $this->account->getAccountName(),
    ];

    // Initialize and return our PHP Agent.
    return new Agent(
      $this->config,
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->config['active'];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured() {
    $is_configured = TRUE;

    $required_settings = [
      'appName',
      'serverUrl',
      'secretToken',
      'apmVersion',
    ];
    foreach ($required_settings as $key) {
      if (empty($this->config[$key])) {
        $is_configured = FALSE;
        break;
      }
    }

    return $is_configured;
  }

}
