<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;

use PhilKra\Agent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
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
   * The elastic_apm configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

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
   * The PHP agent for the Elastic APM.
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $account,
    RouteMatchInterface $route_match,
    LoggerInterface $logger,
    MessengerInterface $messenger
  ) {
    $this->config = $config_factory->get('elastic_apm.configuration');
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->logger = $logger;
    $this->messenger = $messenger;

    // Initialize our PHP Agent.
    // Fetch the configs.
    $elastic_config = $this->config->get();
    // Set the apmVersion to v1 if it's empty as the PHP Agent doesn't.
    if (empty($elastic_config['apmVersion'])) {
      $elastic_config['apmVersion'] = 'v1';
    }

    $this->phpAgent = new Agent(
      $elastic_config,
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
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
      KernelEvents::TERMINATE => ['onTerminate', 300],
    ];

    return $events;
  }

  /**
   * Start a transaction for the PHP Agent whenever this event is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event object.
   *
   * @throws \PhilKra\Exception\Transaction\DuplicateTransactionNameException
   */
  public function onRequest(GetResponseEvent $event) {
    // If this is a sub request, only process it if there was no master
    // request yet. In that case, it is probably a page not found or access
    // denied page.
    if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST && $this->processedMasterRequest) {
      return;
    }

    // Start a new transaction.
    try {
      $transaction = $this->phpAgent->startTransaction($this->routeMatch->getRouteName());

      // Capture database time.
      $transaction->setSpans($this->constructDatabaseSpans());

      // Send our transaction to Elastic.
      $this->phpAgent->send();
    }
    catch (\Exception $e) {
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
   * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
   *   The terminated event object.
   *
   * @throws \PhilKra\Exception\Transaction\UnknownTransactionException
   */
  public function onTerminate(PostResponseEvent $event) {
    // End the transaction.
    try {
      $this->phpAgent->stopTransaction($this->routeMatch->getRouteName());

      // Send our transaction to Elastic.
      $this->phpAgent->send();
    }
    catch (\Exception $e) {
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
        // Add necessary schema info for the APM server.
        $query['name'] = $query['caller']['function'];
        $query['type'] = 'db.mysql.query';
        $query['database'] = $key;
        // Change duration time to milliseconds.
        $query['duration'] = $query['time'] * 1000;
        $query['start'] = 0;

        $spans[] = $query;
      }
    }

    return $spans;
  }

}
