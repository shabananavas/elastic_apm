<?php

namespace Drupal\elastic_apm;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountProxyInterface;

use PhilKra\Agent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Elastic APM service object.
 *
 * @package Drupal\elastic_apm
 */
class ApiService implements ApiServiceInterface {

  /**
   * The Elastic APM configuration config array.
   *
   * @var array
   */
  protected $config;

  /**
   * The Elastic APM connection_settings config array.
   *
   * @var array
   */
  protected $connectionSettings;

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
  protected $pathMatcher;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Constructs an Elastic APM object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AccountProxyInterface $account,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    RequestStack $request_stack,
    CurrentPathStack $current_path
  ) {
    $this->connectionSettings = $configFactory->get('elastic_apm.connection_settings')->get();
    $this->config = $configFactory->get('elastic_apm.configuration')->get();
    $this->account = $account;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->requestStack = $request_stack;
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionSettings() {
    $connection_settings = $this->connectionSettings;

    // Add the Drupal framework details here.
    $connection_settings += [
      'framework' => 'Drupal',
      'frameworkVersion' => Drupal::VERSION,
    ];

    return $connection_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgent(array $options = []) {
    // Add the user info to the options, if it has NOT been opted out.
    if ($this->getConfig()['sendUserDetails']) {
      $options['user'] = [
        'id' => $this->account->id(),
        'username' => $this->account->getAccountName(),
        'email' => $this->account->getEmail(),
      ];
    }

    // Initialize and return our PHP Agent.
    return new Agent(
      $this->getConnectionSettings(),
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->getConnectionSettings()['active'];
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
    $config = $this->getConnectionSettings();
    foreach ($required_settings as $key) {
      if (empty($config[$key])) {
        $is_configured = FALSE;
        break;
      }
    }

    return $is_configured;
  }

  /**
   * {@inheritdoc}
   */
  public function capturePage() {
    // Convert path to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $pages = mb_strtolower($this->getConfig()['pages']);
    if (!$pages) {
      return TRUE;
    }

    // Are the pages supposed to be excluded from the requests?
    $negate = $this->getConfig()['negatePages'] ?: FALSE;

    $request = $this->requestStack->getCurrentRequest();
    // Compare the lowercase path alias (if any) and internal path.
    $path = $this->currentPath->getPath($request);
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

    $path_match = $this->pathMatcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages));

    return (!$path_match || ($path_match && !$negate));
  }

}
