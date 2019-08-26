<?php

namespace Drupal\elastic_apm\EventSubscriber;

use Drupal\elastic_apm\ApiServiceInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ElasticApm exception event subscriber class.
 *
 * @package Drupal\elastic_apm\EventSubscriber
 */
class ExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The Elastic APM service object.
   *
   * @var \Drupal\elastic_apm\ApiServiceInterface
   */
  protected $apiService;

  /**
   * The actual PHP Agent.
   *
   * @var \PhilKra\Agent
   */
  protected $phpAgent;

  /**
   * Constructs a new ExceptionSubscriber object.
   *
   * @param \Drupal\elastic_apm\ApiServiceInterface $api_service
   *   The Elastic APM service object.
   */
  public function __construct(ApiServiceInterface $api_service) {
    $this->apiService = $api_service;

    // Initialize the PHP agent only if Elastic APM is enabled and configured.
    if (!$this->apiService->isPhpAgentEnabled()) {
      return;
    }
    if (!$this->apiService->isPhpAgentConfigured()) {
      return;
    }

    $this->phpAgent = $this->apiService->getPhpAgent();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::EXCEPTION => ['onException', -300],
    ];
  }

  /**
   * Send the exception to the PHP Agent whenever an exception is triggered.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The request event object.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    // Return if Elastic isn't enabled.
    if (!$this->apiService->isPhpAgentEnabled()) {
      return;
    }

    // Don't process if Elastic APM is not configured.
    if (!$this->apiService->isPhpAgentConfigured()) {
      return;
    }

    // Only log the exception if the captureThrowable config is checked.
    if (!$this->apiService->captureThrowable()) {
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
