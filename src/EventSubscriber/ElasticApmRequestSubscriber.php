<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;

use Drupal\elastic_apm\ElasticApmInterface;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ElasticApm request event subscriber class.
 *
 * @package Drupal\elastic_apm\EventSubscriber
 */
class ElasticApmRequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Elastic APM service object.
   *
   * @var \Drupal\elastic_apm\ElasticApmInterface
   */
  protected $elasticApm;

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
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * Constructs a ElasticApmRequestSubscriber object.
   *
   * @param \Drupal\elastic_apm\ElasticApmInterface $elastic_apm
   *   The Elastic APM service object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ElasticApmInterface $elastic_apm,
    RouteMatchInterface $route_match,
    LoggerInterface $logger,
    MessengerInterface $messenger
  ) {
    $this->elasticApm = $elastic_apm;
    $this->routeMatch = $route_match;
    $this->logger = $logger;
    $this->messenger = $messenger;

    // Initialize the PHP agent if the Elastic APM config is configured.
    if ($this->elasticApm->isConfigured()) {
      $this->phpAgent = $this->elasticApm->getAgent();
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

    return $events;
  }

  /**
   * Start a transaction for the PHP Agent whenever this event is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event object.
   */
  public function onRequest(GetResponseEvent $event) {
    // Don't process if Elastic APM is not configured.
    if (!$this->elasticApm->isConfigured()) {
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
      // Notify the user of the error.
      $this->messenger->addError(t('An error occurred while trying to send the transaction to the Elastic APM server.'));

      // Log the error to watchdog.
      $error = Error::decodeException($e);
      $this->logger->log($error['severity_level'], '%type: @message in %function (line %line of %file).', $error);
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
    // Don't process if Elastic APM is not configured.
    if (!$this->elasticApm->isConfigured()) {
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
      // Notify the user of the error.
      $this->messenger->addError(t('An error occurred while trying to send the transaction to the Elastic APM server.'));

      // Log the error to watchdog.
      $error = Error::decodeException($e);
      $this->logger->log($error['severity_level'], '%type: @message in %function (line %line of %file).', $error);
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
            'instance' => $key,
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

        $spans[] = $span;
      }
    }

    return $spans;
  }

}
