<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;

use PhilKra\Agent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ElasticApm request event subscriber class.
 *
 * @package Drupal\elastic_apm\EventSubscriber
 */
class ElasticApmRequestSubscriber implements EventSubscriberInterface {

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
   * The PHP agent for the Elastic APM.
   *
   * @var \PhilKra\Agent
   */
  protected $phpAgent;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $account,
    RouteMatchInterface $route_match,
    RequestStack $request_stack
  ) {
    $this->config = $config_factory->get('elastic_apm.configuration');
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;

    // Initialize our PHP Agent.
    $this->phpAgent = new Agent(
      $this->config->get(),
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
    // Run after RouterListener, which has priority 32.
    return [KernelEvents::REQUEST => ['onRequest', 30]];

    return $events;
  }

  /**
   * Send statistics to the PHP Agent whenever the request event is triggered.
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
    $transaction = $this->phpAgent->startTransaction($this->routeMatch->getRouteName());

    // Create a span to capture the database time.
    $spans = [];
    // TODO: Figure out what kind of db info goes here.
    $spans[] = [

    ];

    // Add the span to the transaction.
    $transaction->setSpans($spans);

    // Send our transaction to Elastic.
    $this->phpAgent->send();

    // Set processedMasterRequest to TRUE.
    $this->processedMasterRequest = TRUE;
  }

}
