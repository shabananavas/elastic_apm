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
class MonitoringSettingsForm extends ConfigFormBase {

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
    $config = $this->config('elastic_apm.settings')->get('monitoring');

    $form['paths'] = [
      '#type' => 'details',
      '#title' => $this->t('Paths'),
      '#open' => TRUE,
    ];

    $path_help = $this->t(
      'Configure the internal paths for which transactions will be sent to the
      Elastic APM server.'
    );
    $path_help .= '<p>';
    $path_help .= '<strong>' . $this->t('Guidelines') . '</strong><br>';
    $path_help .= $this->t(
      'Use ":" to separate the path from the tag and "|" to separate the tag key
      from its value.'
    ) . '<br>';
    $path_help .= $this->t(
      'The "*" character used in the path is a wildcard.'
    ) . '<br>';
    $path_help .= $this->t('Enter one path per line.') . '<br>';
    $path_help .= $this->t('%front is the front page', ['%front' => '<front>']);
    $path_help .= '</p>';
    $path_help .= '<p>';
    $path_help .= '<strong>' . $this->t('Examples') . '</strong>';
    $path_help .= '<br>' .
      '/node/*<br>' .
      '/user/*<br>' .
      '/cart<br>' .
      '/checkout/*';
    $path_help .= '</p>';
    $form['paths']['path_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths to monitor'),
      '#default_value' => $config['paths']['patterns'],
      '#description' => $path_help,
    ];
    $form['paths']['path_negate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Negate'),
      '#default_value' => (int) $config['paths']['negate'],
      '#title_display' => 'invisible',
      '#options' => [
        $this->t('Monitor only the listed paths'),
        $this->t('Monitor all paths except from the listed ones'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set(
        'monitoring.paths',
        [
          'patterns' => $values['path_patterns'],
          'negate' => $values['path_negate'],
        ]
      )
      ->save();

    parent::submitForm($form, $form_state);
  }

}
