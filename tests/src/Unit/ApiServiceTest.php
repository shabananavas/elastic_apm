<?php

namespace Drupal\Tests\elastic_apm\Unit;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;

use Drupal\elastic_apm\ApiService;

use PhilKra\Agent;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ApiServiceTest.
 *
 * @coversDefaultClass \Drupal\elastic_apm\ApiService
 * @group elastic_apm
 *
 * @package Drupal\Tests\elastic_apm\Unit
 */
class ApiServiceTest extends UnitTestCase {

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The account proxy interface.
   *
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    $this->container = new ContainerBuilder();

    $this->account = $this->prophesize(AccountProxyInterface::class);
    $this->account->id()->willReturn(101);
    $this->account->getEmail()->willReturn('user101@example.com');
    $this->account->getAccountName()->willReturn('Test User');
    $this->account = $this->account->reveal();
    $this->container->set('current_user', $this->account);

    \Drupal::setContainer($this->container);
  }

  /**
   * Tests the captureThrowable method.
   *
   * ::covers captureThrowable.
   */
  public function testCaptureThrowable() {
    // Test captureThrowable is FALSE.
    $configs = [
      'captureThrowable' => FALSE,
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertFalse($api_service->captureThrowable());

    // Test captureThrowable is TRUE.
    $configs = [
      'captureThrowable' => TRUE,
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertTrue($api_service->captureThrowable());
  }

  /**
   * Tests the getAgent method.
   *
   * ::covers getAgent.
   */
  public function testGetAgent() {
    // Test getAgent.
    $configs = [
      'appName' => 'Test Elastic',
    ];
    $api_service = $this->fetchApiService($configs);
    $agent = $api_service->getAgent([]);

    $this->assertInstanceOf(Agent::class, $agent);
    $this->assertEquals('Test Elastic', $agent->getConfig()->get('appName'));
    $this->assertEquals('Drupal', $agent->getConfig()->get('framework'));
    $this->assertEquals(Drupal::VERSION, $agent->getConfig()
      ->get('frameworkVersion'));
  }

  /**
   * Tests the isEnabled method.
   *
   * ::covers isEnabled.
   */
  public function testIsEnabled() {
    // Test isEnabled when active is set to FALSE.
    $configs = [
      'active' => FALSE,
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertFalse($api_service->isEnabled());

    // Test isEnabled when active is set to TRUE.
    $configs = [
      'active' => TRUE,
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertTrue($api_service->isEnabled());
  }

  /**
   * Tests the isConfigured method.
   *
   * ::covers isConfigured.
   */
  public function testIsConfigured() {
    // Test isConfigured when required configs aren't set.
    $configs = [];
    $api_service = $this->fetchApiService($configs);

    $this->assertFalse($api_service->isConfigured());

    // Test isConfigured when all but one of the required configs are set.
    $configs = [
      'appName' => 'Test Elastic',
      'secretToken' => 'Mysecrettoken',
      'apmVersion' => '1.0.42',
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertFalse($api_service->isConfigured());

    // Test isConfigured when required configs are set.
    $configs = [
      'appName' => 'Test Elastic',
      'serverUrl' => 'http://apm-server.example.com',
      'secretToken' => 'Mysecrettoken',
      'apmVersion' => '1.0.42',
    ];
    $api_service = $this->fetchApiService($configs);

    $this->assertTrue($api_service->isConfigured());
  }

  /**
   * Constructs and returns a new ApiService class.
   *
   * @param array $configs
   *   An array of configs to pass to the ImmutableConfig object.
   *
   * @return \Drupal\elastic_apm\ApiService
   *   An initialized ApiService class.
   */
  protected function fetchApiService($configs) {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get()->willReturn($configs);
    $config_factory = $this->prophesize(
      ConfigFactoryInterface::class
    );
    $config_factory->get('elastic_apm.connection_settings')
      ->willReturn($config->reveal());
    $config_factory = $config_factory->reveal();

    return new ApiService($config_factory, $this->account);
  }

}
