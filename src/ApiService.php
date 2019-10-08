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
  public function isPhpAgentEnabled() {
    return $this->config['phpAgent']['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTagConfig() {
    return $this->config['tags'];
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

  /**
   * {@inheritdoc}
   */
  public function parseTagPatterns($tag_config) {
    $tag_patterns = [];

    // Explode tag config by new line.
    $pattern_array = explode(PHP_EOL, $tag_config);

    foreach ($pattern_array as $pattern_item) {
      $pattern_array = explode(':', $pattern_item);
      $tag_item = explode('|', $pattern_array['1']);

      $tag_patterns[] = [
        'pattern' => $pattern_array['0'],
        'tag_key' => $tag_item['0'],
        'tag_value' => $tag_item['1'],
      ];
    }

    return $tag_patterns;
  }

}
