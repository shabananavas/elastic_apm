<?php

namespace Drupal\Tests\elastic_apm\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;

use Drupal\elastic_apm\ApiService;
use Drupal\elastic_apm\EventSubscriber\RequestSubscriber;

use PhilKra\Agent;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\Routing\Route;

/**
 * Class RequestSubscriberTest.
 *
 * @coversDefaultClass \Drupal\elastic_apm\EventSubscriber\RequestSubscriber
 * @group elastic_apm
 *
 * @package Drupal\Tests\elastic_apm\Unit
 */
class RequestSubscriberTest extends UnitTestCase {

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    // Initialize the classes needed for the RequestSubscriber class.
    $this->logger = $this->prophesize(LoggerInterface::class);
    $this->logger = $this->logger->reveal();

    $this->time = $this->prophesize(TimeInterface::class);
    $this->time->getCurrentMicroTime()->willReturn(1234567.89);
    $this->time->getRequestMicroTime()->willReturn(3456789.98);
    $this->time = $this->time->reveal();
  }

  /**
   * Tests the prepareAgentOptions method.
   *
   * ::covers prepareAgentOptions.
   */
  public function testPrepareAgentOptions() {
    // Test prepareAgentOptions with no options set in the route.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );

    $this->assertArrayEquals($agent_options, $this->invokeMethod(
      $request_subscriber,
      'prepareAgentOptions',
      []
    ));

    // Test prepareAgentOptions with options set in the route.
    $agent_options = ['tags' => ['is_admin_route' => TRUE]];
    $route_options = [
      '_admin_route' => TRUE,
    ];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );

    $this->assertArrayEquals($agent_options, $this->invokeMethod(
      $request_subscriber,
      'prepareAgentOptions',
      []
    ));
  }

  /**
   * Tests the constructQuerySpan method.
   *
   * ::covers constructQuerySpan.
   */
  public function testConstructQuerySpan() {
    // Test constructQuerySpan.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );

    $connection_name = 'testConnectionName';
    $driver_name = 'testDriverName';
    $expected_result = $this->getExpectedSpanData(
      $connection_name,
      $driver_name,
      $this->getQueryForSpan()
    );
    $actual_result = $this->invokeMethod(
      $request_subscriber,
      'constructQuerySpan',
      [
        $connection_name,
        $driver_name,
        $this->getQueryForSpan(),
      ]
    );
    $this->assertArrayEquals($expected_result, $actual_result);
  }

  /**
   * Tests the constructDatabaseSpans method.
   *
   * ::covers constructDatabaseSpans.
   */
  public function testConstructDatabaseSpans() {
    // Test constructDatabaseSpans.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );

    $this->assertArrayEquals([], $this->invokeMethod(
      $request_subscriber,
      'constructDatabaseSpans',
      []
    ));
  }

  /**
   * Tests the onRequest method.
   *
   * ::covers onRequest.
   */
  public function testOnRequestFailures() {
    // Test onRequest when Elastic APM service is not enabled.
    $agent_options = [];
    $api_service_options = ['enabled' => FALSE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );
    $event = $this->prophesize(GetResponseEvent::class);
    $event = $event->reveal();

    $this->assertNull($request_subscriber->onRequest($event));

    // Test onRequest when Elastic APM service is not configured.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => FALSE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );
    $event = $this->prophesize(GetResponseEvent::class);
    $event = $event->reveal();

    $this->assertNull($request_subscriber->onRequest($event));
  }

  /**
   * Tests the onKernelTerminate method.
   *
   * ::covers onKernelTerminate.
   */
  public function testOnKernelTerminateFailures() {
    // Test onKernelTerminate when Elastic APM service is not enabled.
    $agent_options = [];
    $api_service_options = ['enabled' => FALSE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );
    $event = $this->prophesize(PostResponseEvent::class);
    $event = $event->reveal();

    $this->assertNull($request_subscriber->onKernelTerminate($event));

    // Test onKernelTerminate when Elastic APM service is not configured.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => FALSE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );
    $event = $this->prophesize(PostResponseEvent::class);
    $event = $event->reveal();

    $this->assertNull($request_subscriber->onKernelTerminate($event));
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * ::covers getSubscribedEvents.
   */
  public function testGetSubscribedEvents() {
    // Test getSubscribedEvents.
    $agent_options = [];
    $api_service_options = ['enabled' => TRUE, 'configured' => TRUE];
    $route_options = [];
    $request_subscriber = $this->fetchRequestSubscriber(
      $agent_options,
      $api_service_options,
      $route_options
    );

    $expected_result = [
      'kernel.request' => ['onRequest', 30],
      'kernel.terminate' => ['onKernelTerminate', 300],
    ];
    $this->assertArrayEquals($expected_result, $this->invokeMethod(
      $request_subscriber,
      'getSubscribedEvents',
      []
    ));
  }

  /**
   * Creates an array of span data that would be expected given a query array.
   *
   * @param string $connection
   *   The database connection name.
   * @param string $driver
   *   The database connection driver name.
   * @param array $query
   *   An array of information about the query that was run.
   *
   * @return array
   *   An array of span data.
   */
  protected function getExpectedSpanData($connection, $driver, array $query) {
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
        'filename' => substr(
          $query['caller']['file'],
          strrpos($query['caller']['file'], '/') + 1
        ),
        'lineno' => $query['caller']['line'],
        'vars' => $query['args'],
      ],
    ];

    return $span;
  }

  /**
   * Returns an array of dummy query data.
   *
   * @return array
   *   An array of dummy query data.
   */
  protected function getQueryForSpan() {
    return [
      'caller' => [
        'class' => 'testClassName',
        'function' => 'testFunctionName',
        'file' => 'testFileFolder/testFileName',
        'line' => '1045',
      ],
      'time' => '1001',
      'query' => 'SELECT * FROM DUMMY_TABLE LIMIT 1',
      'args' => [
        'user' => 101,
      ],
    ];
  }

  /**
   * Constructs and returns a new RequestSubscriber class.
   *
   * @param array $agent_options
   *   An array of options to send to the Elastic APM agent.
   * @param array $api_service_options
   *   An array of options to send to the API Service object.
   * @param array $route_options
   *   An array of options to send to the Route object.
   *
   * @return \Drupal\elastic_apm\EventSubscriber\RequestSubscriber
   *   An initialized RequestSubscriber object.
   */
  protected function fetchRequestSubscriber(
    $agent_options,
    $api_service_options,
    $route_options
  ) {
    $agent = $this->prophesize(Agent::class);
    $agent = $agent->reveal();
    $api_service = $this->prophesize(ApiService::class);
    $api_service->isEnabled()->willReturn($api_service_options['enabled']);
    $api_service->isConfigured()
      ->willReturn($api_service_options['configured']);
    $api_service->getAgent($agent_options)->willReturn($agent);
    $api_service = $api_service->reveal();

    $route = $this->prophesize(Route::class);
    $route->getOptions()->willReturn($route_options);
    $route = $route->reveal();
    $route_match = $this->prophesize(RouteMatchInterface::class);
    $route_match->getRouteObject()->willReturn($route);
    $route_match = $route_match->reveal();

    return new RequestSubscriber(
      $api_service,
      $route_match,
      $this->logger,
      $this->time
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
