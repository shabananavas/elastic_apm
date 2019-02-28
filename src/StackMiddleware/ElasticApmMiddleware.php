<?php

namespace Drupal\elastic_apm\StackMiddleware;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class ElasticApmMiddleware.
 *
 * Log all database queries.
 *
 * @package Drupal\elastic_apm\StackMiddleware
 */
class ElasticApmMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a ElasticApmMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    foreach (Database::getAllConnectionInfo() as $key => $info) {
      Database::startLog('elastic_apm', $key);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
