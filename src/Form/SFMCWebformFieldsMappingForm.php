<?php

namespace Drupal\sfmc_connector\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Admin form for SFMC Webform Fields Mapping Settings.
 */
class SFMCWebformFieldsMappingForm extends ConfigFormBase {

  const SFMC_WEBFORM_MAPPING_SETTINGS = 'sfmc_connector.webform_mapping.settings';
  const SFMC_WEBFORM_MAPPING_FORM_ID = 'sfmc_webform_mapping_connector_settings';
  const SFMC_SUBSCRIPTION_SETTINGS = 'sfmc_connector.subscription.settings';
  const SFMC_WEBFORM_HANDLER_ID = 'salesforce_sfmc_subscription';

  /**
   * @var EntityFieldManagerInterface
   */
  private $fieldManager;

  /**
   * @var EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * @param EntityFieldManagerInterface $field_manager
   */
  public function __construct(EntityFieldManagerInterface $field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->fieldManager = $field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::SFMC_WEBFORM_MAPPING_FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SFMC_WEBFORM_MAPPING_SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormTitle() {
    return t('Salesforce API Settings');
  }

  private function updateWebformHandler($webform, $add = TRUE) {
    $handler_manager = \Drupal::service('plugin.manager.webform.handler');

    $handler_configuration = [
      'id' => 'salesforce_sfmc_subscription',
      'label' => 'Salesforce(SFMC) Subscription',
      'handler_id' => 'salesforce_sfmc_subscription',
      'status' => 1,
      'weight' => 0,
      'settings' => [],
    ];

    $handler = $handler_manager->createInstance(self::SFMC_WEBFORM_HANDLER_ID, $handler_configuration);
    $webform->setOriginalId($webform->id());
    $add ? $webform->addWebformHandler($handler) : $webform->deleteWebformHandler($handler);;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_WEBFORM_MAPPING_SETTINGS);
    $subConfig = $this->config(self::SFMC_SUBSCRIPTION_SETTINGS);
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple(NULL);

    if (!empty($webforms)) {
      foreach ($webforms as $webform) {
        $form['webform_fieldset_'.$webform->id()] = [
          '#type' => 'details',
          '#title' => $webform->label(),
          '#open' => FALSE,
        ];

        $form['webform_fieldset_'.$webform->id()][$webform->id().'_webform_mapping_enable'] = [
          '#type' => 'checkbox',
          '#title' => $this->t(sprintf('Enable %s webform for Subscription?', $webform->label())),
          '#description' => $this->t('If checked, user entity fields will be mapped and available for salesforce subscription.'),
          '#return_value' => TRUE,
          '#default_value' => $config->get($webform->id().'_webform_mapping_enable'),
        ];

        $form['webform_fieldset_'.$webform->id()][$webform->id().'_optouts_enable'] = [
          '#type' => 'checkbox',
          '#title' => $this->t(sprintf('Enable %s webform OptOuts option?', $webform->label())),
          '#description' => $this->t('If checked, OptOuts attribute will be added in API Payload in case of empty value of OptIns.'),
          '#return_value' => TRUE,
          '#default_value' => $config->get($webform->id().'_optouts_enable'),
        ];

        $form['webform_fieldset_'.$webform->id()][$webform->id().'_webform_sub_id'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Subscription Id'),
          '#description' => $this->t('If leave empty, it will take subscription id from API Settings.'),
          '#return_value' => TRUE,
          '#default_value' => empty($config->get($webform->id().'_webform_sub_id')) ? $subConfig->get('subscription_id') : $config->get($webform->id().'_webform_sub_id'),
        ];

        $webformElements = $webform->getElementsDecodedAndFlattened();

        foreach ($webformElements as $machineName => $webformElement) {
          if(!($machineName === 'actions' || $webformElement['#type'] == 'webform_actions')) {
            $form['webform_fieldset_'.$webform->id()]['webform_element_' . $webform->id() . '_' . $machineName] = [
              '#type' => 'textfield',
              '#title' => $webformElement['#title'],
              '#default_value' => $config->get('webform_element_' . $webform->id() . '_' . $machineName),
            ];
          }
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple(NULL);

    if (!empty($webforms)) {
      $formEmailError = true;
      $formOptIdError = true;
      foreach ($webforms as $webform) {

        $webformElements = $webform->getElementsDecodedAndFlattened();
        foreach ($webformElements as $machineName => $webformElement) {
          if(!($machineName === 'actions' || $webformElement['#type'] == 'webform_actions')
            && $form_state->getValue('webform_element_' . $webform->id() . '_' . $machineName) === 'OptIns') {
            $formOptIdError = false;
          }
          elseif(!($machineName === 'actions' || $webformElement['#type'] == 'webform_actions')
            && $form_state->getValue('webform_element_' . $webform->id() . '_' . $machineName) === 'EmailAddress') {
            $formEmailError = false;
          }
        }

        if (($formEmailError || $formOptIdError) && $form_state->getValue($webform->id().'_webform_mapping_enable')){
          $form_state->setErrorByName($webform->id().'_webform_mapping_enable', $this->t(sprintf("'%s' webform must have 'EmailAddress' & 'OptIns' attribute in one of mapping field.", $webform->label())));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SFMC_WEBFORM_MAPPING_SETTINGS);
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple(NULL);

    if (!empty($webforms)) {
      foreach ($webforms as $webform) {
        $webformElements = $webform->getElementsDecodedAndFlattened();
        $this->updateWebformHandler($webform, $form_state->getValue($webform->id().'_webform_mapping_enable'));
        $config->set($webform->id().'_webform_mapping_enable', $form_state->getValue($webform->id().'_webform_mapping_enable'));
        $config->set($webform->id().'_optouts_enable', $form_state->getValue($webform->id().'_optouts_enable'));
        $config->set($webform->id().'_webform_sub_id', $form_state->getValue($webform->id().'_webform_sub_id'));
        foreach ($webformElements as $machineName => $webformElement) {
          if(!($machineName === 'actions' || $webformElement['#type'] == 'webform_actions')) {
            $config->set('webform_element_' . $webform->id() . '_' . $machineName, $form_state->getValue('webform_element_' . $webform->id() . '_' . $machineName));
          }
        }
      }

      $config->save();
    }

    parent::submitForm($form, $form_state);
  }

}
