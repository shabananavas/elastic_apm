<?php

namespace Drupal\elastic_apm;

/**
 * Interface for the APIService object.
 *
 * @package Drupal\elastic_apm
 */
interface ApiServiceInterface {

  /**
   * Returns whether Elastic APM is configured to capture errors and exceptions.
   *
   * @return array
   *   TRUE if errors and exceptions should be captured, FALSE otherwise.
   */
  public function captureThrowable();

  /**
   * Returns an initialized Elastic APM PHP Agent.
   *
   * @param array $options
   *   An array of options to pass to the agent when initializing. Ie. tags.
   *
   * @return \PhilKra\Agent
   *   The Elastic APM PHP agent.
   */
  public function getPhpAgent(array $options);

  /**
   * Returns TRUE if the user has enabled Elastic APM PHP Agent.
   *
   * @return bool
   *   TRUE if it is enabled, FALSE otherwise.
   */
  public function isPhpAgentEnabled();

  /**
   * Returns the tag configuration array.
   *
   * @return array
   *   The tag configuration array.
   */
  public function getTagConfig();

  /**
   * Returns TRUE if the Elastic APM php agent settings are configured.
   *
   * @return bool
   *   TRUE if the php agent settings are configured, FALSE otherwise.
   */
  public function isPhpAgentConfigured();

  /**
   * Returns the parsed tag patterns array.
   *
   * It ignores malformed patterns.
   *
   * @param string $tag_config
   *   The tag config string.
   *
   * @return array
   *   The parsed tag config array.
   */
  public function parseTagPatterns($tag_config);

  /**
   * Validates the given tag pattern.
   *
   * Checks whether the given pattern follows the expected format:
   * pattern:tag-key|tag-value
   * /product/*:provider|commerce
   * entity.commerce_product.canonical:provider|commerce
   *
   * @param string $pattern
   *   The pattern to validate.
   *
   * @return string[]
   *   Returns an array containing the malformed patterns, if any were found.
   */
  public function validateTagPattern($pattern);

}
