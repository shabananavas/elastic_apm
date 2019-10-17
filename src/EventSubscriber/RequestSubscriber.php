<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\elastic_apm\ApiServiceInterface;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ElasticApm request event subscriber class.
 *
 * @package Drupal\elastic_apm\EventSubscriber
 */
class RequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Elastic APM service object.
   *
   * @var \Drupal\elastic_apm\ApiServiceInterface
   */
  protected $apiService;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The actual PHP Agent for the Elastic APM server.
   *
   * @var \PhilKra\Agent
   */
  protected $phpAgent;

  /**
   * The system time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * A flag whether the master request was processed.
   *
   * @var bool
   */
  protected $processedMasterRequest = FALSE;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private $pathMatcher;

  /**
   * Constructs a RequestSubscriber object.
   *
   * @param \Drupal\elastic_apm\ApiServiceInterface $api_service
   *   The Elastic APM service object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time service.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.
   */
  public function __construct(
    ApiServiceInterface $api_service,
    RouteMatchInterface $route_match,
    LoggerInterface $logger,
    TimeInterface $time,
    PathMatcherInterface $pathMatcher
  ) {
    $this->apiService = $api_service;
    $this->routeMatch = $route_match;
    $this->logger = $logger;
    $this->time = $time;
    $this->pathMatcher = $pathMatcher;

    // Initialize the PHP agent if the Elastic APM config is configured.
    if ($this->apiService->isPhpAgentEnabled() && $this->apiService->isPhpAgentConfigured()) {
      // Let's pass some options to the Agent depending on the request.
      $this->phpAgent = $this->apiService->getPhpAgent($this->prepareAgentOptions());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => [['onRequest', 30]],
      KernelEvents::FINISH_REQUEST => [['onFinishRequest', 300]],
    ];
  }

  /**
   * Start a transaction for the PHP Agent whenever this event is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event object.
   */
  public function onRequest(GetResponseEvent $event) {
    // Return if Elastic isn't enabled.
    if (!$this->apiService->isPhpAgentEnabled()) {
      return;
    }

    // Don't process if Elastic APM is not configured.
    if (!$this->apiService->isPhpAgentConfigured()) {
      return;
    }

    // If this is a sub request, only process it if there was no master
    // request yet. In that case, it is probably a page not found or access
    // denied page.
    $is_master = $event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST;
    if ($is_master && $this->processedMasterRequest) {
      return;
    }

    // Start a new transaction.
    try {
      $transaction = $this->phpAgent
        ->startTransaction($this->routeMatch->getRouteName());

      // Capture database time by wrapping spans around the db queries run.
      $transaction->setSpans($this->constructDatabaseSpans());
    }
    catch (Exception $e) {
      // Log the error.
      $this->logger->error(
        sprintf(
          'An error occurred while trying to start a transaction for the Elastic
           APM server. The error was: "%s".',
          $e->getMessage()
        )
      );
    }

    // Mark the request as processed.
    $this->processedMasterRequest = TRUE;
  }

  /**
   * End the transaction and send to PHP Agent whenever this event is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
   *   The event to process.
   */
  public function onFinishRequest(FinishRequestEvent $event) {
    // Return if Elastic isn't enabled.
    if (!$this->apiService->isPhpAgentEnabled()) {
      return;
    }

    // Don't process if Elastic APM is not configured.
    if (!$this->apiService->isPhpAgentConfigured()) {
      return;
    }

    // Don't process if we don't have a PHP Agent already initialized, meaning,
    // no transaction is in process.
    if (!$this->phpAgent) {
      return;
    }

    // End the transaction.
    try {
      $this->phpAgent->stopTransaction($this->routeMatch->getRouteName());

      // Send our transaction to Elastic.
      $this->phpAgent->send();
    }
    catch (Exception $e) {
      // Log the error.
      $this->logger->error(
        sprintf(
          'An error occurred while stopping and sending the transaction to the
           Elastic APM server. The error was: "%s".',
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Create spans around the db queries that were run in this request.
   *
   * @return array
   *   An array of spans that will be added to the transaction.
   */
  protected function constructDatabaseSpans() {
    $spans = [];

    foreach (Database::getAllConnectionInfo() as $key => $connection) {
      $driver = current($connection)['driver'];
      $database_log = Database::startLog('elastic_apm', $key);
      foreach ($database_log->get('elastic_apm') as $query) {
        $spans[] = $this->constructQuerySpan($key, $driver, $query);
      }
    }

    return $spans;
  }

  /**
   * Create a span for an individual database query.
   *
   * @param string $connection
   *   The database connection name.
   * @param string $driver
   *   The database connection driver name.
   * @param array $query
   *   An array of information about the query that was run.
   *
   * @return array
   *   An array of necessary information about the query to send to Elastic.
   */
  protected function constructQuerySpan($connection, $driver, array $query) {
    $span = [];

    $start = $this->time->getCurrentMicroTime() - $this->time->getRequestMicroTime();

    // Add the necessary schema info for the APM server.
    $span['name'] = $query['caller']['class'] . '::' . $query['caller']['function'];
    $span['type'] = 'db.' . $driver . '.query';
    // Start time relative to the transaction start in milliseconds.
    $span['start'] = $start * 1000;
    $span['duration'] = $query['time'];
    $span['context'] = [
      'db' => [
        'instance' => $connection,
        'statement' => $query['query'],
        'type' => 'sql',
      ],
    ];
    $span['stacktrace'] = [
      [
        'function' => $query['caller']['function'],
        'abs_path' => $query['caller']['file'],
        'filename' => substr($query['caller']['file'], strrpos($query['caller']['file'], '/') + 1),
        'lineno' => $query['caller']['line'],
        'vars' => $query['args'],
      ],
    ];

    return $span;
  }

  /**
   * Add options to pass to the PHP Agents.
   *
   * Currently we are just tagging admin pages.
   *
   * @return array
   *   An array of options to pass to the PHP Agent, such as tags.
   */
  protected function prepareAgentOptions() {
    $tags = [];

    $route_options = $this->routeMatch->getRouteObject()->getOptions();
    if (isset($route_options['_admin_route'])) {
      $tags['is_admin_route'] = TRUE;
    }

    // Add tags based on the path patterns configured.
    $tags = $tags + $this->preparePathPatternTags();
    $tags = $tags + $this->prepareRoutePatternTags();

    return ['tags' => $tags];
  }

  /**
   * Provide tags based on the path patterns configured.
   *
   * @return array
   *   An array of tags to pass to the PHP Agent.
   */
  protected function preparePathPatternTags() {
    $tags = [];

    // Fetch the current path from route object.
    $path = $this->routeMatch->getRouteObject()->getPath();

    // Fetch the configured path patterns.
    $tag_config = $this->apiService->getTagConfig();

    if (!$tag_config['path_patterns']) {
      return [];
    }

    $patterns = $this->apiService
      ->parseTagPatterns($tag_config['path_patterns']);

    // Add tags depending on the path pattern set.
    foreach ($patterns as $pattern) {
      // If the configured path does not match with the current path
      // continue to look for the next pattern.
      if (!$this->pathMatcher->matchPath($path, $pattern['pattern'])) {
        continue;
      }

      $tags[$pattern['tag_key']] = $pattern['tag_value'];
    }

    return $tags;
  }

  /**
   * Provide tags based on the route patterns configured.
   *
   * @return array
   *   An array of tags to pass to the PHP Agent.
   */
  protected function prepareRoutePatternTags() {
    $tags = [];

    // Fetch the current path from route object.
    $route = $this->routeMatch->getRouteName();

    // Fetch the configured path patterns.
    $tag_config = $this->apiService->getTagConfig();

    if (!$tag_config['route_patterns']) {
      return [];
    }

    $patterns = $this->apiService
      ->parseTagPatterns($tag_config['route_patterns']);

    // Add tags depending on the path pattern set.
    foreach ($patterns as $pattern) {
      // If the configured route does not match with the current route
      // continue to look for the next pattern.
      if (!$this->matchRoute($route, $pattern['pattern'])) {
        continue;
      }

      $tags[$pattern['tag_key']] = $pattern['tag_value'];
    }

    return $tags;
  }

  /**
   * Checks if a route matches any pattern in a set of patterns.
   *
   * @param string $route
   *   The route to match.
   * @param string $pattern
   *   The pattern string.
   *
   * @return bool
   *   TRUE if the route matches the pattern, FALSE otherwise.
   */
  protected function matchRoute($route, $pattern) {
    if (!isset($this->regexes[$pattern])) {
      // Convert path settings to a regular expression.
      $to_replace = [
        // Quote asterisks.
        '/\\\\\*/',
      ];
      $replacements = [
        '.*',
      ];
      $patterns_quoted = preg_quote($pattern, '/');
      $this->regexes[$pattern] = '/^(' . preg_replace($to_replace, $replacements, $patterns_quoted) . ')$/';
    }

    return (bool) preg_match($this->regexes[$pattern], $route);
  }

}
