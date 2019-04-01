<?php

namespace Drupal\elastic_apm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for the Elastic APM.
 *
 * @package Drupal\elastic_apm\Form
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elastic_apm_configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['elastic_apm.configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elastic_apm.configuration');

    $form['request_path'] = [
      '#type' => 'details',
      '#title' => $this->t('Request paths'),
      '#open' => TRUE,
    ];

    $form['request_path']['pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Include/Exclude pages'),
      '#default_value' => $config->get('pages'),
      '#description' => $this->t(
        "Specify pages to include/exclude when capturing requests, by
         using their paths. Enter one path per line. The '*' character is a
          wildcard. An example path is %admin-wildcard for omitting all admin
           pages. %front is the front page.", [
        '%admin-wildcard' => '/admin/*',
        '%front' => '<front>',
      ]),
    ];

    $form['request_path']['negate_pages'] = [
      '#type' => 'radios',
      '#title' => $this->t('Pages'),
      '#default_value' => (int) $config->get('negatePages'),
      '#title_display' => 'invisible',
      '#options' => [
        $this->t('Capture requests for the listed pages'),
        $this->t('Don\'t capture requests for the listed pages'),
      ],
    ];

    $form['errors']['capture_exceptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture errors and exceptions'),
      '#default_value' => $config->get('captureExceptions'),
    ];

    $form['user_details'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send user details'),
      '#description' => $this->t(
        'Checking this will send the logged-in user\'s uid, username, and
         email along with each request to Elastic.'
      ),
      '#default_value' => $config->get('sendUserDetails'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('elastic_apm.configuration')
      ->set('pages', $values['pages'])
      ->set('negatePages', (bool) $values['negate_pages'])
      ->set('captureExceptions', $values['capture_exceptions'])
      ->set('sendUserDetails', $values['user_details'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
