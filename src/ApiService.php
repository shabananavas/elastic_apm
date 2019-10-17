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
     // Add the info to the options if its configured to send.
    if ($this->config['privacy']['track_user']) {
      $options['user'] = [
        'id' => $this->account->id(),
        'email' => $this->account->getEmail(),
        'username' => $this->account->getAccountName(),
      ];
    }

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
    $valid_patterns = [];

    // Get an array of all patterns, one per line in the given string.
    $patterns = explode(PHP_EOL, $tag_config);

    foreach ($patterns as $pattern) {
      $pattern_parts = explode(':', $pattern);

      if (empty($pattern_parts['1'])) {
        continue;
      }

      $tag_parts = explode('|', $pattern_parts['1']);

      // Ignore the pattern if there is no tag value defined for it.
      if (empty($tag_parts['1'])) {
        continue;
      }

      $valid_patterns[] = [
        'pattern' => trim($pattern_parts['0']),
        'tag_key' => trim($tag_parts['0']),
        'tag_value' => trim($tag_parts['1']),
      ];
    }

    return $valid_patterns;
  }

  /**
   * {@inheritdoc}
   */
  public function validateTagPattern($pattern) {
    $pattern_parts = array_filter(explode(':', $pattern), 'trim');

    if (count($pattern_parts) !== 2) {
      return FALSE;
    }

    $tag_parts = array_filter(explode('|', $pattern_parts['1']), 'trim');

    // Ignore the pattern if there is no tag value defined for it.
    if (count($tag_parts) !== 2) {
      return FALSE;
    }

    return TRUE;
  }

}
