<?php

namespace Drupal\elastic_apm\Form;

use Drupal\elastic_apm\ApiServiceInterface;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page monitor settings form for Elastic APM.
 *
 * Contains path pattern settings for configuring which pages to
 * include/exclude from elastic APM monitoring.
 *
 * @package Drupal\elastic_apm\Form
 */
class PageMonitorSettingsForm extends ConfigFormBase {

  /**
   * The Elastic APM service object.
   *
   * @var \Drupal\elastic_apm\ApiServiceInterface
   */
  protected $apiService;

  /**
   * Constructs a new tag settings form object.
   *
   * @param Drupal\elastic_apm\ApiServiceInterface $api_service
   *   The elastic api service.
   */
  public function __construct(ApiServiceInterface $api_service) {
    $this->apiService = $api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('elastic_apm.api_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_page_monirot_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['elastic_apm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elastic_apm.settings')->get('page_monitor');

    $form['page_monitor'] = [
      '#type' => 'details',
      '#title' => $this->t('Page Monitor settings'),
      '#open' => TRUE,
    ];
    $form['page_monitor']['path_pattern'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Pattern'),
      '#default_value' => $config['path']['pattern'],
      '#description' => $this->t('
        Specify pages to include when capturing requests, by
        using their paths. Enter one path per line. The "*" character is a
        wildcard. An example path is /admin/* for monitoring all admin
        pages. <front> is the front page.
      '),
    ];
    $form['page_monitor']['negate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Path Pattern'),
      '#default_value' => (int) $config['path']['negate'],
      '#title_display' => 'invisible',
      '#options' => [
        $this->t('Monitor the listed pages'),
        $this->t('Do not monitor the listed pages'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set('page_monitor.path', [
        'pattern' => $values['path_pattern'],
        'negate' => $values['negate'],
      ])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Validates the tag patterns string.
   *
   * Ensures that the patterns are entered in the expected format
   * /product/*: provider|commerce
   *
   * @param string $patterns_string
   *   The patterns string.
   *
   * @return bool
   *   True if the provided pattern is valid, False otherwise.
   */
  private function validateTagPatterns($patterns_string) {
    $patterns = $this->apiService
      ->parseTagPatterns($patterns_string);

    // If we cannot find a single pattern we return FALSE
    if (empty($patterns['0'])) {
      return FALSE;
    }

    // If all the required key is present in the pattern string, we return TRUE.
    if (
        $patterns['0']['pattern'] &&
        $patterns['0']['tag_key'] &&
        $patterns['0']['tag_value']
      ) {
      return TRUE;
    }

    return FALSE;

  }

}
