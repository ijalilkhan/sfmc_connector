<?php

namespace Drupal\sfmc_connector\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Create salesforce(sfmc) subscription from a webform submission.
 *
 * @WebformHandler(
 *   id = "salesforce_sfmc_subscription",
 *   label = @Translation("Salesforce(SFMC) Subscription"),
 *   category = @Translation("Salesforce Subscription"),
 *   description = @Translation("Create salesforce(sfmc) subscription from a webform submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */

class SFMCWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $webform = $webform_submission->getWebform();
    $webformSubmissionData = $webform_submission->getData();

    if (!isset($webformSubmissionData['sfmc_status'])) {
      $webformData[$webform->id()] = $webformSubmissionData;
      $subscription =  \Drupal::service('sfmc_connector.salesforce_service')->subscription($webformData, 'webform');
      if (!empty($subscription)) {
        $data = $webformSubmissionData;
        $data['sfmc_status'] = 'submitted';
        $webform_submission->setData($data);
      }
    }
  }
}
