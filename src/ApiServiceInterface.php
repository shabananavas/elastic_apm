<?php


namespace Drupal\elastic_apm;

/**
 * Interface for the APIService object.
 *
 * @package Drupal\elastic_apm
 */
interface ApiServiceInterface {

  /**
   * Returns the Elastic APM configuration array.
   *
   * @return array
   *   An array of Elastic APM configuration.
   */
  public function getConfig();

  /**
   * Returns the Elastic APM connection_settings array.
   *
   * @return array
   *   An array of Elastic APM connection_settings.
   */
  public function getConnectionSettings();

  /**
   * Returns an initialized Elastic APM PHP Agent.
   *
   * @param array $options
   *   An array of options to pass to the agent when initializing. Ie. tags.
   * @return \PhilKra\Agent
   *   The Elastic APM PHP agent.
   */
  public function getAgent(array $options);

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

  /**
   * Returns TRUE if the current page is in the include pages list.
   *
   * @return bool
   *   TRUE if the current page is in the include list.
   */
  public function capturePage();

}
