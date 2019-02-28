<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\elastic_apm\ElasticApmInterface;

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
   * The Elastic APM service object.
   *
   * @var \Drupal\elastic_apm\ElasticApmInterface
   */
  protected $elasticApm;

  /**
   * The actual PHP Agent.
   *
   * @var \PhilKra\Agent
   */
  protected $phpAgent;

  /**
   * Constructs a ElasticApmExceptionSubscriber object.
   *
   * @param \Drupal\elastic_apm\ElasticApmInterface $elastic_apm
   *   The Elastic APM service object.
   */
  public function __construct(ElasticApmInterface $elastic_apm) {
    $this->elasticApm = $elastic_apm;

    // Fetch our initialized PHP agent.
    $this->phpAgent = $this->elasticApm->getAgent();
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
    // Don't process if Elastic APM is not configured.
    if (!$this->elasticApm->isConfigured()) {
      return;
    }

    // Only log the exception if the capture_exceptions config is checked.
    if (!$this->elasticApm->getConfig()['capture_exceptions']) {
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
