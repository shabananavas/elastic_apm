<?php

namespace Drupal\elastic_apm;

use Drupal;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * An alias manager to find the alias for the current system path.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private $pathMatcher;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs an Elastic APM object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $account,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    RouteMatchInterface $route_match
  ) {
    $this->account = $account;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->routeMatch = $route_match;

    $this->config = $config_factory->get('elastic_apm.settings')->get();
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
  public function getMonitoringConfig() {
    return $this->config['monitoring'];
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
  public function shouldMonitor() {
    $result = $this->shouldMonitorPath();
    if ($result === NULL) {
      return TRUE;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldMonitorPath() {
    $config = $this->getMonitoringConfig();

    $pattern_array = array_filter(
      explode(PHP_EOL, $config['paths']['patterns']),
      'trim'
    );
    if (!$pattern_array) {
      return NULL;
    }

    // Are the pages supposed to be excluded from the requests?
    $negate = (bool) $config['paths']['negate'];

    // Get the current path.
    $path = $this->routeMatch->getRouteObject()->getPath();

    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');

    foreach ($pattern_array as $pattern) {
      $match = $this->pathMatcher->matchPath($path, $pattern);
      if (!$match) {
        continue;
      }

      return $negate ? FALSE : TRUE;
    }

    return $negate ? TRUE : FALSE;
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
