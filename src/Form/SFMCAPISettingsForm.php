<?php

namespace Drupal\sfmc_connector\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for SFMC Subscription Settings.
 */
class SFMCAPISettingsForm extends ConfigFormBase {

  const SFMC_API_SETTINGS = 'sfmc_connector.api.settings';
  const SFMC_API_FORM_ID = 'sfmc_api_connector_settings';
  const SFMC_API_SCOPE = 'data_extensions_read data_extensions_write';
  /**
   * @var EntityFieldManagerInterface
   */
  private $fieldManager;

  /**
   * @param EntityFieldManagerInterface $field_manager
   */
  public function __construct(EntityFieldManagerInterface $field_manager) {
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::SFMC_API_FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SFMC_API_SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormTitle() {
    return t('Salesforce API Settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_API_SETTINGS);

    $form['grant_type'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Grant Type'),
      '#default_value' => $config->get('grant_type'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client Id'),
      '#default_value' => $config->get('client_id'),
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#size' => 5,
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
    ];

    $form['scope'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Scope'),
      '#default_value' => !empty($config->get('scope')) ? $config->get('scope') : self::SFMC_API_SCOPE,
    ];

    $form['account_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Account Id'),
      '#default_value' => $config->get('account_id'),
    ];

    $form['auth_developer_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Developer Auth Endpoint'),
      '#default_value' => $config->get('auth_developer_endpoint'),
    ];

    $form['auth_production_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Production Auth Endpoint'),
      '#default_value' => $config->get('auth_production_endpoint'),
    ];

    $form['token_expiry_time'] = [
      '#type' => 'textfield',
      //'#disabled' => TRUE,
      '#title' => $this->t('Token Expiry Time'),
      '#default_value' => $config->get('token_expiry_time'),
    ];

    $form['last_generated_token_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Generated Token Time'),
      '#default_value' => $config->get('last_generated_token_time'),
    ];
    $form['current_access_token'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#title' => $this->t('Current Access Token'),
      '#default_value' => $config->get('current_access_token'),
    ];


    $form['is_prod'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable Production Endpoint?',
      '#description' => 'If checked, production endpoint will be used for API calls & Subscriptions.',
      '#return_value' => TRUE,
      '#default_value' => $config->get('is_prod'),
    ];

    $form['api_debugger'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable API debugger?',
      '#description' => 'If checked, response will be logged in db log.',
      '#return_value' => TRUE,
      '#default_value' => $config->get('api_debugger'),
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_API_SETTINGS);

    $config->set('grant_type', $form_state->getValue('grant_type'));
    $config->set('client_id', $form_state->getValue('client_id'));
    $config->set('client_secret', $form_state->getValue('client_secret'));
    $config->set('scope', $form_state->getValue('scope'));
    $config->set('account_id', $form_state->getValue('account_id'));
    $config->set('auth_developer_endpoint', $form_state->getValue('auth_developer_endpoint'));
    $config->set('auth_production_endpoint', $form_state->getValue('auth_production_endpoint'));
    $config->set('is_prod', $form_state->getValue('is_prod'));
    $config->set('token_expiry_time', $form_state->getValue('token_expiry_time'));
    $config->set('api_debugger', $form_state->getValue('api_debugger'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
