/**
 * @file
 * Attaches the script that initializes the Elastic APM RUM agent.
 */

(function($, Drupal, drupalSettings) {
  'use strict';

  elasticApm.init({
    serviceName: drupalSettings.elasticApm.rum.serviceName,
    serverUrl: drupalSettings.elasticApm.rum.serverUrl,
    serviceVersion: drupalSettings.elasticApm.rum.serviceVersion
  });

})(jQuery, Drupal, drupalSettings);
