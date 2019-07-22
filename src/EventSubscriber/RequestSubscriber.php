<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\elastic_apm\ApiServiceInterface;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\MessengerInterface;
use \Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

const ELASTIC_APM_PARENT_ROUTES = [
  'commerce',
  'config',
  'node',
  'reports',
  'search',
  'structure',
  'user'
];

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
   * A flag whether the master request was processed.
   *
   * @var bool
   */
  protected $processedMasterRequest = FALSE;

  /**
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
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.\
   */
  public function __construct(
    ApiServiceInterface $api_service,
    RouteMatchInterface $route_match,
    LoggerInterface $logger,
    MessengerInterface $messenger,
    PathMatcherInterface $pathMatcher
  ) {
    $this->apiService = $api_service;
    $this->routeMatch = $route_match;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->pathMatcher = $pathMatcher;

    // Initialize the PHP agent if the Elastic APM config is configured.
    if ($this->apiService->isEnabled() && $this->apiService->isConfigured()) {
      // Let's pass some options to the Agent depending on the request.
      $this->phpAgent = $this->apiService->getAgent($this->getRequestOptions());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
      KernelEvents::FINISH_REQUEST => ['onFinishRequest', 300],
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
    if (!$this->apiService->isEnabled()) {
      return;
    }

    // Don't process if Elastic APM is not configured.
    if (!$this->apiService->isConfigured()) {
      return;
    }

    // If this is a sub request, only process it if there was no master
    // request yet. In that case, it is probably a page not found or access
    // denied page.
    if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST && $this->processedMasterRequest) {
      return;
    }

    // Start a new transaction.
    try {
      $transaction = $this->phpAgent->startTransaction($this->routeMatch->getRouteName());

      // Capture database time by wrapping spans around the db queries run.
      $transaction->setSpans($this->constructDatabaseSpans());
    }
    catch (Exception $e) {
      // Log the error to watchdog.
      $this->logger->error($this->t(
        'An error occurred while trying to start a transaction for the Elastic APM server. The error was @error.', [
        '@error' => $e->getMessage(),
      ]));
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
    if (!$this->apiService->isEnabled()) {
      return;
    }

    // Don't process if Elastic APM is not configured.
    if (!$this->apiService->isConfigured()) {
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
      // Log the error to watchdog.
      $this->logger->error($this->t(
        'An error occurred while trying to send the transaction to the Elastic APM server. The error was @error.', [
          '@error' => $e->getMessage(),
      ]));
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

    // First, go through all queries that have been run for this request.
    $connections = [];
    foreach (Database::getAllConnectionInfo() as $key => $info) {
      $database = Database::getConnection('default', $key);
      $connections[$key] = $database->getLogger()->get('elastic_apm');
    }

    // Now, create a span for each query that was run.
    foreach ($connections as $key => $queries) {
      foreach ($queries as $query) {
        $spans[] = $this->constructQuerySpan($key, $query);
      }
    }

    return $spans;
  }

  /**
   * Create a span for an individual database query.
   *
   * @param string $connection
   *   The database connection name.
   * @param array $query
   *   An array of information about the query that was run.
   *
   * @return array
   *   An array of necessary information about the query to send to Elastic.
   */
  protected function constructQuerySpan($connection, array $query) {
    $span = [];

    // Add the necessary schema info for the APM server.
    $span['name'] = $query['caller']['function'];
    $span['type'] = 'db.mysql.query';
    // This is the query start time relative to the transaction start.
    $span['start'] = 0;
    // Change duration time of query to milliseconds.
    $span['duration'] = $query['time'] * 1000;
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
   * @return array
   *   An array of options to pass to the PHP Agent. Ie. tags.
   */
  protected function getRequestOptions() {
    $options = [];

    $tag_config = $this->apiService->getTagConfig();
    $route_pattern = Yaml::decode($tag_config['route_pattern']);
    $path_pattern = Yaml::decode($tag_config['path_pattern']);

    // Fetch the current path.
    $route_object = $this->routeMatch->getRouteObject();
    $path = $route_object->getPath();
    $route_options = $route_object->getOptions();

    $route_name = $this->routeMatch->getRouteName();

    // If this is an admin page, add a custom variable to denote that.
    if (isset($route_options['_admin_route'])) {
      $options['tags']['admin_page'] = TRUE;
    }

    // Add tags depending on the route pattern set.
    foreach ($route_pattern as $key => $tag) {
      if (!($this->matchRoute($route_name, $key))) {
        continue;
      }

      $options['tags']['parent_route'] = $tag;
    }

    // Add tags depending on the path pattern set.
    // Path patterns take priority over route pattern.
    foreach ($path_pattern as $key => $tag) {
      if (!($this->pathMatcher->matchPath($path, $key))) {
        continue;
      }

      $options['tags']['parent_route'] = $tag;
    }

    return $options;
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
