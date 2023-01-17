<?php

namespace Drupal\sfmc_connector\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for SFMC User Fields Mapping Settings.
 */
class SFMCUserFieldsMappingForm extends ConfigFormBase {

  const SFMC_USER_MAPPING_SETTINGS = 'sfmc_connector.user_mapping.settings';
  const SFMC_USER_MAPPING_FORM_ID = 'sfmc_user_mapping_connector_settings';
  const SFMC_SUBSCRIPTION_SETTINGS = 'sfmc_connector.subscription.settings';

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
    return self::SFMC_USER_MAPPING_FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SFMC_USER_MAPPING_SETTINGS,
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
    $config = $this->config(self::SFMC_USER_MAPPING_SETTINGS);
    $subConfig = $this->config(self::SFMC_SUBSCRIPTION_SETTINGS);
    $userFields = $this->fieldManager->getFieldDefinitions('user', 'user');

    $form['user_mapping_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable User Entity for Subscription?'),
      '#description' => $this->t('If checked, user entity fields will be mapped and available for salesforce subscription.'),
      '#return_value' => TRUE,
      '#default_value' => $config->get('user_mapping_enable'),
    ];

    $form['user_webform_sub_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscription Id'),
      '#description' => $this->t('If empty, it will take subscription id from API Settings.'),
      '#return_value' => TRUE,
      '#default_value' => empty($config->get('user_webform_sub_id')) ? $subConfig->get('subscription_id') : $config->get('user_webform_sub_id'),
    ];

    foreach ($userFields as $machineName => $userField) {
      if (strpos($machineName, 'field_') !== FALSE) {
        $form['user_'. $machineName] = [
          '#type' => 'textfield',
          '#title' => $this->t($userField->getLabel() . ' Field Mapping'),
          '#default_value' => $config->get('user_'. $machineName),
        ];
      }
    }



    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $userFields = $this->fieldManager->getFieldDefinitions('user', 'user');
    $formOptError = TRUE;

    foreach ($userFields as $machineName => $userField) {
      if (strpos($machineName, 'field_') !== FALSE && $form_state->getValue('user_'. $machineName) === 'OptIns') {
        $formOptError = FALSE;
      }
    }

    if ($formOptError && $form_state->getValue('user_mapping_enable')) {
      $form_state->setErrorByName('user_mapping_enable', $this->t("User fields must have 'OptIns' attribute in one of mapping field."));
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_USER_MAPPING_SETTINGS);
    $userFields = $this->fieldManager->getFieldDefinitions('user', 'user');

    foreach ($userFields as $machineName => $userField) {
      if (strpos($machineName, 'field_') !== FALSE) {
        $config->set('user_'. $machineName, $form_state->getValue('user_'. $machineName));
      }
    }

    $config->set('user_mapping_enable', $form_state->getValue('user_mapping_enable'));
    $config->set('user_webform_sub_id', $form_state->getValue('user_webform_sub_id'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
