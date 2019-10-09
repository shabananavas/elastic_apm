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
   * The current path.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private $pathMatcher;

  /**
   * Constructs an Elastic APM object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AccountProxyInterface $account,
    AliasManagerInterface $alias_manager,
    RouteMatchInterface $route_match,
    PathMatcherInterface $pathMatcher
  ) {
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->pathMatcher = $pathMatcher;
    $this->aliasManager = $alias_manager;

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
  public function getPageMonitorConfig() {
    return $this->config['page_monitor'];
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

      // Early return if the path pattern is not set correctly.
      if (empty($pattern_array['1'])) {
        return;
      }

      $tag_item = explode('|', $pattern_array['1']);

      $tag_patterns[] = [
        'pattern' => $pattern_array['0'],
        'tag_key' => !empty($tag_item['0']) ? $tag_item['0'] : '',
        'tag_value' => !empty($tag_item['1']) ? $tag_item['1'] : '',
      ];
    }

    return $tag_patterns;
  }


  /**
   * {@inheritdoc}
   */
  public function monitorPage() {
    $config = $this->getPageMonitorConfig();

    $pattern_array = explode(PHP_EOL, $config['path']['pattern']);
    if (!$pattern_array) {
      return TRUE;
    }

    // Are the pages supposed to be excluded from the requests?
    $negate = $config['path']['negate'] ?: FALSE;

    // Get the current path.
    $path = $this->routeMatch->getRouteObject()->getPath();

    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

    foreach ($pattern_array as $pattern) {
      if (
        $this->pathMatcher->matchPath($path, $pattern) ||
        $this->pathMatcher->matchPath($path_alias, $pattern)) {
        // If we get a path match and negate is configured, we do not monitor
        // the page.
        if ($negate) {
          return FALSE;
        }

        return TRUE;
      }
    }

    return FALSE;
  }

}
