<?php

namespace Drupal\elastic_apm\Form;

use Drupal\elastic_apm\ApiServiceInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    $config = $this->config('elastic_apm.settings')->get('tags');

    $form['user_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add user role tags'),
      '#description' => $this->t(
        'When checked, tags will be added to transactions indicating whether the
        visitor initiating the request is an anonymous/ authenticated user.'
      ),
      '#default_value' => empty($config['user_role']) ? FALSE : TRUE,
    ];

    $form['paths'] = [
      '#type' => 'details',
      '#title' => $this->t('Paths'),
      '#open' => TRUE,
    ];

    $path_help = $this->t('
      Configure the tags to add to the transactions based on the internal path of
      the request being served.
    ');
    $path_help .= '<p>';
    $path_help .= '<strong>' . $this->t('Guidelines') . '</strong><br>';
    $path_help .= $this->t(
      'Use ":" to separate the path from the tag and "|" to separate the tag key
      from its value.'
    ) . '<br>';
    $path_help .= $this->t(
      'The "*" character used in the path is a wildcard.'
    ) . '<br>';
    $path_help .= $this->t('Enter one path/tag combination per line.') . '<br>';
    $path_help .= $this->t(
      'Each tag key can only have one value. If a path ending up having multiple
      values for the same key as a result of multiple patterns, the last one will
      be used.'
    ) . '<br>';
    $path_help .= '</p>';
    $path_help .= '<p>';
    $path_help .= '<strong>' . $this->t('Examples') . '</strong>';
    $path_help .= '<br>' .
      '/node/* &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : provider|node<br>' .
      '/checkout/* &nbsp;&nbsp;&nbsp;: provider|commerce<br>' .
      '/checkout/* &nbsp;&nbsp;&nbsp;: ux-group|cart-and-checkout<br>' .
      '/cart &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : ux-group|cart-and-checkout<br>' .
      '/custom-path : feature|custom-feature';
    $path_help .= '</p>';
    $form['paths']['path_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tags per path'),
      '#default_value' => $config['path_patterns'],
      '#description' => $path_help,
    ];

    $form['routes'] = [
      '#type' => 'details',
      '#title' => $this->t('Routes'),
      '#open' => TRUE,
    ];

    $route_help = $this->t('
      Configure the tags to add to the transactions based on the route of the
      request being served.
    ');
    $route_help .= '<p>';
    $route_help .= '<strong>' . $this->t('Guidelines') . '</strong><br>';
    $route_help .= $this->t(
      'Use ":" to separate the route from the tag and "|" to separate the tag key
      from its value.'
    ) . '<br>';
    $route_help .= $this->t(
      'The "*" character used in the route is a wildcard.'
    ) . '<br>';
    $route_help .= $this->t('Enter one route/tag combination per line.') . '<br>';
    $route_help .= $this->t(
      'Each tag key can only have one value. If a route ending up having multiple
      values for the same key as a result of multiple patterns, the last one will
      be used.'
    ) . '<br>';
    $route_help .= '</p>';
    $route_help .= '<p>';
    $route_help .= '<strong>' . $this->t('Examples') . '</strong>';
    $route_help .= '<br>' .
      'entity.node.* &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : provider|node<br>' .
      'commerce_checkout.form &nbsp;&nbsp;&nbsp;: provider|commerce<br>' .
      'commerce_checkout.form &nbsp;&nbsp;&nbsp;: ux-group|cart-and-checkout<br>' .
      'commerce_cart.page &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : ux-group|cart-and-checkout<br>' .
      'custom.route : feature|custom-feature';
    $route_help .= '</p>';
    $form['routes']['route_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tags per route'),
      '#default_value' => $config['route_patterns'],
      '#description' => $route_help,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!$values['path_patterns'] && !$values['route_patterns']) {
      return;
    }

    $this->setPatternErrors(
      $values['path_patterns'],
      $form['paths']['path_patterns'],
      $form_state
    );
    $this->setPatternErrors(
      $values['route_patterns'],
      $form['routes']['route_patterns'],
      $form_state
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.settings')
      ->set(
        'tags',
        [
          'path_patterns' => $values['path_patterns'],
          'route_patterns' => $values['route_patterns'],
          'user_role' => (bool) $values['user_role'],
        ]
      )
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Set an error message for the given form element in the case of error.
   *
   * @param string $patterns_string
   *   A string containing all patterns that will be validated, as stored in the
   *   configuration.
   */
  protected function setPatternErrors(
    $patterns_string,
    array &$element,
    FormStateInterface $form_state
  ) {
    if (!$patterns_string) {
      return [];
    }

    $invalid_patterns = $this->validateTagPatterns($patterns_string);
    if (!$invalid_patterns) {
      return;
    }

    $markup = '<ul>';
    foreach ($invalid_patterns as $pattern) {
      $markup .= '<li>' . $pattern . '</li>';
    }
    $markup .= '</ul>';
    $text = 'The following patterns are malformed. Please correct them and try again.';
    $message = new TranslatableMarkup(
      '@text' . $markup,
      ['@text' => $text]
    );
    $form_state->setError(
      $element,
      $message
    );
  }

  /**
   * Validates the tag patterns string.
   *
   * Checks whether all given patterns follow the expected format:
   * pattern:tag-key|tag-value
   * /product/*:provider|commerce
   *
   * @param string $patterns_string
   *   The patterns string.
   *
   * @return string[]
   *   Returns an array containing the malformed patterns, if any were found.
   */
  private function validateTagPatterns($patterns_string) {
    // An empty string passes because it is not mandatory to provide patterns.
    if (!$patterns_string) {
      return [];
    }

    $invalid_patterns = [];

    // Get an array of all patterns, one per line in the given string.
    $patterns_array = array_filter(explode(PHP_EOL, $patterns_string), 'trim');

    foreach ($patterns_array as $pattern) {
      if ($this->apiService->validateTagPattern($pattern)) {
        continue;
      }

      $invalid_patterns[] = $pattern;
    }

    return $invalid_patterns;
  }

}
