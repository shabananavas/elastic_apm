<?php

namespace Drupal\Tests\elastic_apm\Functional;

use Drupal\Tests\BrowserTestBase;
use function uniqid;

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
  public static $modules = ['node', 'elastic_apm'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $admin_user = $this->drupalCreateUser(['access content', 'access administration pages']);
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
    // First, let's save the configs.
    $this->drupalGet('admin/config/development/performance/elastic-apm');
    $this->submitForm([
      'app_name' => ELASTIC_APM_TEST_APP_NAME,
      'server_url' => ELASTIC_APM_TEST_SERVER_URL,
      'secret_token' => ELASTIC_APM_TEST_SECRET_TOKEN,
    ], t('Save configuration'));

    // Now create a page node with a unique title. We make the path unique so
    // that the transaction name will be unique in the APM server as well.
    $this->drupalGet('node/add/page');
    $unique_id = uniqid();
    $title = 'Test Elastic APM' . $unique_id;
    $path_alias = '/test-elastic-apm' . $unique_id;
    $edit = array(
      'title[0][value]' => $title,
      'path[0][alias]' => $path_alias,
    );
    $this->drupalPostForm('node/add/page', $edit, t('Save'));
    $this->assertSession()->statusCodeEquals(200);

    // Make a page request to this new page, so that the transaction will be
    // sent to Elastic.
    $this->drupalGet($path_alias);

    // Now, fetch the transactions made today, from Elastic, to check if our
    // request was actually sent.
    $http_client = \Drupal::httpClient();
    $date = date('Y-m-d');
    $response = $http_client->get(ELASTIC_APM_GET_URL . '/apm-' . ELASTIC_APM_VERSION . '-transaction-' . $date . '/_search');
    $results = json_decode($response->getBody(), TRUE);

    // Verify that there are transactions for this date.
    $this->assertArrayHasKey('hits', $results);
    $this->assertNotEquals(0, $results['hits']['total']);

    // Now go through the array of transactions to see if our page link exists.
    foreach ($results['hits']['hits'] as $key => $hit) {
      if ($hit['transaction']['name'] != 'node.add_page') {
        continue;
      }

      if ($hit['context']['request']['REQUEST_URI'] != $path_alias) {
        continue;
      }

      $this->assertTrue($hit['context']['request']['REQUEST_URI'] == $path_alias, 'Transaction exists in Elastic APM server.');
      $this->assertEquals('acro_dev@example.com', $hit['context']['context']['user']['email']);
    }
  }

}
