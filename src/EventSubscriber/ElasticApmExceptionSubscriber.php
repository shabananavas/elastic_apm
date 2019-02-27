<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

use PhilKra\Agent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ElasticApm exception event subscriber class.
 *
 * @package Drupal\elastic_apm\EventSubscriber
 */
class ElasticApmExceptionSubscriber implements EventSubscriberInterface {

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
   * Constructs a ElasticApmExceptionSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current account.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $account
  ) {
    $this->config = $config_factory->get('elastic_apm.configuration');
    $this->account = $account;

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
    $events[KernelEvents::EXCEPTION][] = ['onException', -300];

    return $events;
  }

  /**
   * Send the exception to the PHP Agent whenever an exception is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The request event object.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    // Only log the exception if the capture_exceptions config is checked.
    if (!$this->config->get('capture_exceptions')) {
      return;
    }

    // Don't log http exceptions.
    if ($event->getException() instanceof HttpExceptionInterface) {
      return;
    }

    // Send the exception to Elastic.
    $this->phpAgent->captureThrowable($event->getException());
    $this->phpAgent->send();
  }

}
