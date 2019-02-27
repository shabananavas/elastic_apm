<?php

namespace Drupal\Tests\elastic_apm\Functional;

use Drupal\Tests\BrowserTestBase;

const ELASTIC_APM_TEST_APP_NAME = 'Dev Test';
const ELASTIC_APM_TEST_SERVER_URL = 'https://90513fabda504d4e803580956a3fb037.apm.us-west-1.aws.cloud.es.io:443';
const ELASTIC_APM_TEST_SECRET_TOKEN = 'VIFTL9svIkv0D9bAHr';
const ELASTIC_APM_GET_URL = 'http://51a1f65937d849558a4ae8c1165cebe2.containerhost:9244';
const ELASTIC_APM_VERSION = '6.6.1';

/**
 * Class ElasticApmTest.
 *
 * @package Drupal\Tests\elastic_apm\Functional
 */
class ElasticApmTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['elastic_apm'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(['access administration pages']);
    $admin_user->setEmail('acro_dev@example.com');
    $this->drupalLogin($admin_user);
  }

  /**
   * Test that we can successfully set the Elastic APM configuration.
   */
  public function testElasticApmConfiguration() {
    $this->drupalGet('admin/config/development/performance/elastic-apm');
    $this->assertSession()->statusCodeEquals(200);
    // Test page contains some text.
    $this->assertSession()->pageTextContains('Elastic APM');

    // Fetch the current configuration and assert that it hasn't been set.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->get('elastic_apm.configuration');
    $app_name = $config->get('appName');
    $server_url = $config->get('serverUrl');
    $secret_token = $config->get('secretToken');
    $this->assertNull($app_name);
    $this->assertNull($server_url);
    $this->assertNull($secret_token);

    $this->submitForm([
      'app_name' => ELASTIC_APM_TEST_APP_NAME,
      'server_url' => ELASTIC_APM_TEST_SERVER_URL,
      'secret_token' => ELASTIC_APM_TEST_SECRET_TOKEN,
    ], t('Save configuration'));

    // Now, ensure the new configs have been saved.
    $app_name = $config->get('appName');
    $server_url = $config->get('serverUrl');
    $secret_token = $config->get('secretToken');
    $this->assertEquals(ELASTIC_APM_TEST_APP_NAME, $app_name);
    $this->assertEquals(ELASTIC_APM_TEST_SERVER_URL, $server_url);
    $this->assertEquals(ELASTIC_APM_TEST_SECRET_TOKEN, $secret_token);
  }

  /**
   * Test that we can successfully send transactions to the Elastic APM server.
   */
  public function testElasticApmTransaction() {
    // First, let's set the configs.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->get('elastic_apm.configuration');

    $config->set('appName', ELASTIC_APM_TEST_APP_NAME)
      ->set('serverUrl', ELASTIC_APM_TEST_SERVER_URL)
      ->set('secretToken', ELASTIC_APM_TEST_SECRET_TOKEN)
      ->save();

    // Make a page request, so that the transaction will be sent to Elastic.
    $this->drupalGet('node/add');
    $this->assertSession()->statusCodeEquals(200);

    // Now, fetch the transactions from Elastic to check if our request was
    // actually sent.
    $http_client = \Drupal::httpClient();
    $date = date('Y-m-d');
    $response = $http_client->get(ELASTIC_APM_GET_URL . '/apm-' . ELASTIC_APM_VERSION . '-transaction-' . $date . '/_search');
    $results = json_decode($response->getBody(), TRUE);

    $this->assertArrayHasKey('hits', $results);
    $this->assertEquals(1, $results['hits']['total']);
    $this->assertEquals('node.add_page', $results['hits']['hits'][0]['transaction']['name']);
    $this->assertNotNull('node.add_page', $results['hits']['hits'][0]['transaction']['id']);
    $this->assertEquals('acro_dev@example.com', $results['hits']['hits'][0]['context']['user']['email']);
  }

}
