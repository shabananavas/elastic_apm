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
class ElasticApm implements ElasticApmInterface {

  /**
   * A config object for the elastic_apm configuration.
   *
   * @var \Drupal\Core\Config\Config
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
    $this->config = $configFactory->get('elastic_apm.configuration');
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgent() {
    // Initialize and return our PHP Agent.
    return new Agent(
      $this->getConfig(),
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
  public function getConfig() {
    // Fetch the configs.
    $elastic_apm_config = $this->config->get();
    // Set the apmVersion to v1 if it's empty as the PHP Agent doesn't.
    if (empty($elastic_apm_config['apmVersion'])) {
      $elastic_apm_config['apmVersion'] = 'v1';
    }

    return $elastic_apm_config;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured() {
    $elastic_apm_config = $this->config->get();

    if (
      !$elastic_apm_config['active']
      || empty($elastic_apm_config['appName'])
      || empty($elastic_apm_config['serverUrl'])
      || empty($elastic_apm_config['secretToken'])
    ) {
      return FALSE;
    }

    return TRUE;
  }

}
