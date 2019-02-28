<?php


namespace Drupal\elastic_apm;

/**
 * Interface for the Elastic APM service object.
 *
 * @package Drupal\elastic_apm
 */
interface ElasticApmInterface {

  /**
   * Returns an initialized Elastic APM PHP Agent.
   *
   * @return \PhilKra\Agent
   *   The Elastic APM PHP agent.
   */
  public function getAgent();

  /**
   * Returns the Elastic APM configuration.
   *
   * @return array
   *   The Elastic configuration.
   */
  public function getConfig();

  /**
   * Returns TRUE if the elastic_apm config object is configured.
   *
   * @return bool
   *   TRUE if the config object is configured.
   */
  public function isConfigured();

}
