<?php

namespace Drupal\sfmc_connector\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for SFMC Subscription Settings.
 */
class SFMCSubscriptionSettingsForm extends ConfigFormBase {

  const SFMC_SUBSCRIPTION_SETTINGS = 'sfmc_connector.subscription.settings';
  const SFMC_SUBSCRIPTION_FORM_ID = 'sfmc_subscription_connector_settings';

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
    return self::SFMC_SUBSCRIPTION_FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SFMC_SUBSCRIPTION_SETTINGS,
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
    $config = $this->config(self::SFMC_SUBSCRIPTION_SETTINGS);

    $form['source_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Source Id'),
      '#default_value' => $config->get('source_id'),
    ];

    $form['subscription_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Subscription Id'),
      '#default_value' => $config->get('subscription_id'),
    ];

    $form['sub_developer_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Developer Subscription Endpoint'),
      '#default_value' => $config->get('sub_developer_endpoint'),
    ];

    $form['sub_production_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Production Subscription Endpoint'),
      '#default_value' => $config->get('sub_production_endpoint'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_SUBSCRIPTION_SETTINGS);
    $userFields = $this->fieldManager->getFieldDefinitions('user', 'user');

    foreach ($userFields as $machineName => $userField) {
      if (strpos($machineName, 'field_') !== FALSE) {
        $config->set('user_'. $machineName, $form_state->getValue('user_'. $machineName));
      }
    }

    $config->set('source_id', $form_state->getValue('source_id'));
    $config->set('subscription_id', $form_state->getValue('subscription_id'));
    $config->set('sub_developer_endpoint', $form_state->getValue('sub_developer_endpoint'));
    $config->set('sub_production_endpoint', $form_state->getValue('sub_production_endpoint'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
