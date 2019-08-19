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
   *   TRUE if it is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Returns TRUE if the Elastic APM connection settings are configured.
   *
   * @return bool
   *   TRUE if the connection settings are configured, FALSE otherwise.
   */
  public function isConfigured();

}
