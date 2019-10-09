<?php

namespace Drupal\elastic_apm\Form;

use Drupal\elastic_apm\ApiServiceInterface;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tag settings form for Elastic APM.
 *
 * Contains path pattern settings and route pattern settings elements.
 *
 * @package Drupal\elastic_apm\Form
 */
class TagSettingsForm extends ConfigFormBase {

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
    return 'elastic_apm_tag_settings';
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
    $config = $this->config('elastic_apm.settings');

    $form['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tag settings'),
      '#open' => TRUE,
    ];
    $form['tags']['path_pattern_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path Pattern Tags'),
      '#default_value' => $config->get('tags')['path_patterns'],
      '#description' => $this->t('
        Configure which tags to send for which pages. Please use : to separate
        the path pattern from the tag and | to separate the key from the value.
        The "*" character is a wildcard. Enter one path per line
        Example:
        <pre>
          /product/*: provider|commerce
          /checkout/*: provider|commerce
        </pre>
      '),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // If path pattern tags are set, ensure that it is entered in the expected
    // format.
    if ($values['path_pattern_tags']) {
      if (!$this->validateTagPatterns($values['path_pattern_tags'])) {
        $form_state->setError(
          $form['tags']['path_pattern_tags'],
          $this->t('Please enter valid path patterns')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set('tags', [
        'path_patterns' => $values['path_pattern_tags'],
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
