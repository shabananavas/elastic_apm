<?php


namespace Drupal\elastic_apm;

/**
 * Interface for the APIService object.
 *
 * @package Drupal\elastic_apm
 */
interface ApiServiceInterface {

  /**
   * Returns an initialized Elastic APM PHP Agent.
   *
   * @return \PhilKra\Agent
   *   The Elastic APM PHP agent.
   */
  public function getAgent();

  /**
   * Returns TRUE if the user has enabled Elastic APM.
   *
   * @return bool
   *   TRUE if it is enabled.
   */
  public function isEnabled();

  /**
   * Returns TRUE if the elastic_apm config object is configured.
   *
   * @return bool
   *   TRUE if the config object is configured.
   */
  public function isConfigured();

}
