<?php

namespace Drupal\Tests\elastic_apm\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

const ELASTIC_APM_TEST_APP_NAME = 'Acro Dev Test';
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
  public static $modules = [
    'elastic_apm',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer site configuration',
      'administer url aliases',
      'create page content',
      'create url aliases',
    ]);
    $admin_user->setEmail('acro_dev@example.com');
    $this->drupalLogin($admin_user);
  }

  /**
   * Test that we can successfully set the Elastic APM configuration.
   */
  public function testElasticApmConfiguration() {
    $session = $this->assertSession();

    $this->drupalGet(Url::fromRoute('elastic_apm.configuration'));
    $session->statusCodeEquals(200);
    // Test page contains some text.
    $session->pageTextContains('Elastic APM');

    // Fetch the current configuration and assert that it hasn't been set.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->get('elastic_apm.configuration');
    $app_name = $config->get('appName');
    $server_url = $config->get('serverUrl');
    $secret_token = $config->get('secretToken');
    $this->assertEmpty($app_name);
    $this->assertEmpty($server_url);
    $this->assertEmpty($secret_token);

    $this->submitForm([
      'app_name' => ELASTIC_APM_TEST_APP_NAME,
      'server_url' => ELASTIC_APM_TEST_SERVER_URL,
      'secret_token' => ELASTIC_APM_TEST_SECRET_TOKEN,
    ], t('Save configuration'));
    $session->statusCodeEquals(200);

    // Now, ensure the new configs have been saved.
    $config = $config_factory->get('elastic_apm.configuration');
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
    $session = $this->assertSession();

    // First, create a page node with a unique title. We make the path unique so
    // that the transaction name will be unique in the APM server as well.
    $this->drupalGet('node/add/page');
    $unique_id = uniqid();
    $title = 'Test Elastic APM ' . $unique_id;
    $path_alias = '/test-elastic-apm-' . $unique_id;
    $edit = array(
      'title[0][value]' => $title,
      'path[0][alias]' => $path_alias,
    );
    $this->drupalPostForm('node/add/page', $edit, t('Save'));
    $session->statusCodeEquals(200);
    $this->drupalGet($path_alias);
    $session->statusCodeEquals(200);

    // Now, let's save the Elastic APM configs, so the requests start
    // submitting.
    $this->drupalGet(Url::fromRoute('elastic_apm.configuration'));
    $this->submitForm([
      'app_name' => ELASTIC_APM_TEST_APP_NAME,
      'server_url' => ELASTIC_APM_TEST_SERVER_URL,
      'secret_token' => ELASTIC_APM_TEST_SECRET_TOKEN,
      'active' => TRUE,
    ], t('Save configuration'));
    $session->statusCodeEquals(200);
    // Test page contains some text.
    $session->pageTextContains('Elastic APM');

    // Make a page request to this new page, so that the transaction will be
    // sent to Elastic.
    $this->drupalGet($path_alias);
    $this->assertSession()->statusCodeEquals(200);

    // Now, fetch the transactions made today, from Elastic, to check if our
    // request was actually sent.
    $date = date('Y.m.d');
    $url = ELASTIC_APM_GET_URL . '/apm-' . ELASTIC_APM_VERSION . '-transaction-' . $date . '/_search';
    $options = [
      'query' => [
        'term' => [
          'context.service.name' => [
            'value' => 'Test New App'
          ]
        ]
      ],
    ];
    $response = $this->getHttpClient()->request('GET', $url, $options);
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
