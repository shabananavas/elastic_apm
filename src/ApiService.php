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
  public function getAgent() {
    // Initialize and return our PHP Agent.
    return new Agent(
      $this->config,
      [
        'user' => [
          'id' => $this->account->id(),
          'email' => $this->account->getEmail(),
        ],
      ]
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
