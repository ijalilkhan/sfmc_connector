<?php

namespace Drupal\sfmc_connector\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * To process the claim for the unclaimed submissions.
 */
class SFMCBatchForm extends FormBase {

  /**
   * @var
   */
  private $nonSubscribedWebforms;

  /**
   * @var
   */
  private $nonSubscribeUsers;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sfmc_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $SFMCService = \Drupal::service('sfmc_connector.salesforce_service');
    $nonSubscribedWebforms = $SFMCService->getNonSubscribedWebforms();
    $nonSubscribeUsers = $SFMCService->getNonSubscribedUsers();

    $this->nonSubscribedWebforms = $nonSubscribedWebforms;
    $this->nonSubscribeUsers = $nonSubscribeUsers;

    $form['non_subscribed_webforms'] = [
      '#markup' => $this->t('Total number of webforms awaiting the subscription: :webform_count</br></br>', [':webform_count' => count($nonSubscribedWebforms)]),
    ];

    $form['non_subscribed_users'] = [
      '#markup' => $this->t('Total number of users awaiting the subscription: :user_count</br></br>', [':user_count' => count($nonSubscribeUsers)]),
    ];

    if ($nonSubscribedWebforms > 0 || $nonSubscribeUsers > 0) {
      $form['process_subscription'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit the pending subscription'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = [
      ['sfmc_connector_batch_process_for_pending', [$this->nonSubscribeUsers, $this->nonSubscribedWebforms]],
    ];

    $batch = [
      'title' => $this->t('Submitting subscription for pending data ...'),
      'operations' => $operations,
      'finished' => 'sfmc_connector_batch_process_finished',
    ];

    batch_set($batch);
  }

}
