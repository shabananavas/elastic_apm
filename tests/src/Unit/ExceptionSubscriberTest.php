<?php

namespace Drupal\Tests\elastic_apm\Unit;

use Drupal\Tests\UnitTestCase;

use Drupal\elastic_apm\ApiService;
use Drupal\elastic_apm\EventSubscriber\ExceptionSubscriber;

use PhilKra\Agent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class ExceptionSubscriberTest.
 *
 * @coversDefaultClass \Drupal\elastic_apm\EventSubscriber\ExceptionSubscriber
 * @group elastic_apm
 *
 * @package Drupal\Tests\elastic_apm\Unit
 */
class ExceptionSubscriberTest extends UnitTestCase {

  /**
   * Tests the onException method.
   *
   * ::covers onException.
   */
  public function testOnExceptionFailures() {
    // Test onException when Elastic APM service is not enabled.
    $options = [
      'enabled' => FALSE,
      'configured' => TRUE,
      'capture_throwable' => TRUE,
    ];
    $exception_subscriber = $this->fetchExceptionSubscriber($options);
    $event = $this->prophesize(GetResponseForExceptionEvent::class);
    $event = $event->reveal();

    $this->assertNull($exception_subscriber->onException($event));

    // Test onException when Elastic APM service is not configured.
    $options = [
      'enabled' => TRUE,
      'configured' => FALSE,
      'capture_throwable' => TRUE,
    ];
    $exception_subscriber = $this->fetchExceptionSubscriber($options);
    $event = $this->prophesize(GetResponseForExceptionEvent::class);
    $event = $event->reveal();

    $this->assertNull($exception_subscriber->onException($event));

    // Test onException when Elastic APM service does not capture exceptions.
    $options = [
      'enabled' => TRUE,
      'configured' => TRUE,
      'capture_throwable' => FALSE,
    ];
    $exception_subscriber = $this->fetchExceptionSubscriber($options);
    $event = $this->prophesize(GetResponseForExceptionEvent::class);
    $event = $event->reveal();

    $this->assertNull($exception_subscriber->onException($event));

    // Test onException when Elastic APM service when the exception is an HTTP
    // exception.
    $options = [
      'enabled' => TRUE,
      'configured' => TRUE,
      'capture_throwable' => TRUE,
    ];
    $exception_subscriber = $this->fetchExceptionSubscriber($options);
    $exception = $this->prophesize(HttpException::class);
    $exception = $exception->reveal();
    $event = $this->prophesize(GetResponseForExceptionEvent::class);
    $event->getException()->willReturn($exception);
    $event = $event->reveal();

    $this->assertNull($exception_subscriber->onException($event));
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * ::covers getSubscribedEvents.
   */
  public function testGetSubscribedEvents() {
    // Test getSubscribedEvents.
    $options = [
      'enabled' => TRUE,
      'configured' => TRUE,
      'capture_throwable' => TRUE,
    ];
    $exception_subscriber = $this->fetchExceptionSubscriber($options);

    $expected_result = [
      'kernel.exception' => ['onException', -300],
    ];
    $this->assertArrayEquals($expected_result, $this->invokeMethod(
      $exception_subscriber,
      'getSubscribedEvents',
      []
    ));
  }

  /**
   * Constructs and returns a new ExceptionSubscriber class.
   *
   * @param array $api_service_options
   *   An array of options that should be passed ot the ApiService class.
   *
   * @return \Drupal\elastic_apm\EventSubscriber\ExceptionSubscriber
   *   An initialized ExceptionSubscriber object.
   */
  protected function fetchExceptionSubscriber($api_service_options) {
    $agent = $this->prophesize(Agent::class);
    $agent = $agent->reveal();
    $api_service = $this->prophesize(ApiService::class);
    $api_service->isEnabled()->willReturn($api_service_options['enabled']);
    $api_service->isConfigured()
      ->willReturn($api_service_options['configured']);
    $api_service->captureThrowable()->willReturn(
      $api_service_options['capture_throwable']
    );
    $api_service->getAgent()->willReturn($agent);
    $api_service = $api_service->reveal();

    return new ExceptionSubscriber(
      $api_service
    );
  }

  /**
   * Call protected/private method of a class.
   *
   * We'll need this special function to test the protected functions.
   *
   * @param object &$object
   *   Instantiated object that we will run method on.
   * @param string $methodName
   *   Method name to call.
   * @param array $parameters
   *   Array of parameters to pass into method.
   *
   * @return mixed
   *   Method return.
   */
  protected function invokeMethod(
    &$object,
    $methodName,
    array $parameters = []
  ) {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($object, $parameters);
  }

}
