<?php

namespace Drupal\Tests\elastic_apm\Unit;

use Drupal\Tests\UnitTestCase;

use Drupal\elastic_apm\ApiService;
use Drupal\elastic_apm\EventSubscriber\ExceptionSubscriber;

use PhilKra\Agent;

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
   * Tests the getSubscribedEvents method.
   *
   * ::covers getSubscribedEvents.
   */
  public function testGetSubscribedEvents() {
    // Test getSubscribedEvents.
    // Create necessary classes to pass to the RequestSubscriber constructor.
    $agent = $this->prophesize(Agent::class);
    $agent = $agent->reveal();
    $api_service = $this->prophesize(ApiService::class);
    $api_service->isEnabled()->willReturn(TRUE);
    $api_service->isConfigured()->willReturn(TRUE);
    $api_service->getAgent()->willReturn($agent);
    $api_service = $api_service->reveal();

    $exception_subscriber = new ExceptionSubscriber(
      $api_service
    );

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
  protected function invokeMethod(&$object, $methodName, array $parameters = []) {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($object, $parameters);
  }

}
