<?php

namespace Drupal\sfmc_connector;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionForm;

class SalesForceAPIService {

  const CONFIG_LAST_GENERATED_TOKEN_TIME = 'last_generated_token_time';

  const CONFIG_CURRENT_ACCESS_TOKEN = 'current_access_token';

  const CONFIG_TOKEN_EXPIRY_TIME = 'token_expiry_time';

  const MODULE_CHANNEL_NAME = 'sfmc_connector';

  const API_HEADERS_ACCEPT_TYPE = 'application/json';

  const WEBFORM_HANDLER_SALESFORCE = 'salesforce_sfmc_subscription';

  /**
   * Http Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $APISettings;

  /**
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $subscriptionSettings;

  /**
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $userMappingSettings;

  /**
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $webformMappingSettings;

  /**
   * @var array|mixed|null
   */
  private $grantType;

  /**
   * @var
   */
  private $clientId;

  /**
   * @var
   */
  private $clientSecret;

  /**
   * @var array|mixed|null
   */
  private $scope;

  /**
   * @var array|mixed|null
   */
  private $accountId;

  /**
   * @var array|mixed|null
   */
  private $accessToken;

  /**
   * @var array|mixed|null
   */
  private $lastTokenTime;

  /**
   * @var array|mixed|null
   */
  private $tokenExpiryTime;

  /**
   * @const
   */
  const API_DEBUGGER_CONFIG = 'api_debugger';

  /**
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   */
  public function __construct(ClientInterface $http_client,
                              LoggerChannelFactoryInterface $logger_factory,
                              ConfigFactory $config_factory,
                              EntityFieldManagerInterface $field_manager,
                              EntityTypeManagerInterface $entity_type_manager) {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->entityTypeManager = $entity_type_manager;

    $this->APISettings = $config_factory->get('sfmc_connector.api.settings');
    $this->grantType = $this->APISettings->get('grant_type');
    $this->clientId = $this->APISettings->get('client_id');
    $this->clientSecret = $this->APISettings->get('client_secret');
    $this->scope = $this->APISettings->get('scope');
    $this->accountId = $this->APISettings->get('account_id');
    $this->accessToken = $this->APISettings->get(self::CONFIG_CURRENT_ACCESS_TOKEN);
    $this->lastTokenTime = $this->APISettings->get(self::CONFIG_LAST_GENERATED_TOKEN_TIME);
    $this->tokenExpiryTime = $this->APISettings->get(self::CONFIG_TOKEN_EXPIRY_TIME);

    $this->subscriptionSettings = $config_factory->get('sfmc_connector.subscription.settings');
    $this->userMappingSettings = $config_factory->get('sfmc_connector.user_mapping.settings');
    $this->webformMappingSettings = $config_factory->get('sfmc_connector.webform_mapping.settings');

  }


  /**
   * @param $userData
   * @param $subscriptionType
   * @return array|mixed|string
   */
  public function subscription($userData, $subscriptionType) {
    $userRequestData = $subscriptionType === 'user' ? $this->prepareUserData($userData) : $this->prepareWebformData($userData);

    if (!empty($userRequestData)) {
      $this->accessToken = $this->generateAccessToken();

      if(!empty($this->accessToken)) {
        return !empty($userRequestData['keys']['EmailAddress']) ? $this->subscriptionCall($userRequestData) : '';
      }
    }

    return '';
  }

  /**
   * @param EntityInterface $user
   * @return array
   */
  protected function prepareUserData(EntityInterface $user) {
    $data = [];
    if (!empty($user) && $this->userMappingSettings->get('user_mapping_enable')) {
      $subscriptionId = empty($this->userMappingSettings->get('user_webform_sub_id')) ? $this->subscriptionSettings->get('subscription_id') : $this->userMappingSettings->get('user_webform_sub_id');
      $userFields = $this->fieldManager->getFieldDefinitions('user', 'user');

      $data['keys'] = [
        'EmailAddress' => $user->getEmail(),
        "SourceID" => $this->subscriptionSettings->get('source_id'),
        "TimeStamp" => date('Y-m-d H:i:s', time())
      ];

      foreach ($userFields as $machineName => $userField) {
        if (strpos($machineName, 'field_') !== FALSE && !empty($this->userMappingSettings->get('user_'. $machineName))) {
          if ($this->userMappingSettings->get('user_'. $machineName) === 'OptIns') {
            if ($user->get($machineName)->getString()) {
              $data['values']['OptIns'] = $subscriptionId;
            } else {
              $data['values']['OptOuts'] = $subscriptionId;
            }
          } else {
            $data['values'][$this->userMappingSettings->get('user_'. $machineName)] = $user->get($machineName)->getString();
          }
        }
      }


    }
    return $data;
  }

  /**
   * @param $webformData
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function prepareWebformData($webformData) {

    $data = [];
    if(!empty($webformData)) {
      $data['keys'] = [
        "SourceID" => $this->subscriptionSettings->get('source_id'),
        "TimeStamp" => date('Y-m-d H:i:s', time())
      ];

      $webformName = array_key_first($webformData);
      $webformData = current($webformData);

      $webform = $this->entityTypeManager->getStorage('webform')->load($webformName);
      if (!empty($webform) && $this->webformMappingSettings->get($webform->id().'_webform_mapping_enable')) {
        $subscriptionId = empty($this->webformMappingSettings->get($webform->id().'_webform_sub_id')) ? $this->subscriptionSettings->get('subscription_id') : $this->webformMappingSettings->get($webform->id().'_webform_sub_id');
        $webformElements = $webform->getElementsDecodedAndFlattened();

        foreach ($webformElements as $machineName => $webformElement) {
          $mappingFieldData = $this->webformMappingSettings->get('webform_element_' . $webform->id() . '_' . $machineName);
          if(!empty($mappingFieldData)) {
            if ($mappingFieldData === 'EmailAddress') {
              $data['keys']['EmailAddress'] = $webformData[$machineName];
            } elseif ($mappingFieldData === 'OptIns') {
              if ($this->webformMappingSettings->get($webform->id().'_optouts_enable')) {
                $optOptions = empty($webformData[$machineName]) ? 'OptOuts' : 'OptIns';
                $data['values'][$optOptions] = $subscriptionId;
              } elseif (!empty($webformData[$machineName])) {
                $data['values']['OptIns'] = $subscriptionId;
              }
            } elseif (!empty($webformData[$machineName])) {
              $data['values'][$mappingFieldData] = $webformData[$machineName];
            }
          }
        }
      }
    }

    return $data;
  }

  /**
   * @return array|mixed|string
   */
  protected function subscriptionCall($data, $multiple_records = false) {
    try {
      $accessApi = $this->APISettings->get('is_prod')
        ? $this->subscriptionSettings->get('sub_production_endpoint')
        : $this->subscriptionSettings->get('sub_developer_endpoint');
      $consumerData = $multiple_records ? $data : [$data];

      $response = $this->httpClient->request('POST', $accessApi, [
        'body' => json_encode($consumerData),
        'headers' => [
          'authorization' => 'Bearer ' . $this->accessToken,
          'content-type' => self::API_HEADERS_ACCEPT_TYPE
        ]
      ]);

      $statusCode = $response->getStatusCode();
      $this->logger('subscriptionCall: $statusCode-' . $statusCode);

      if ($statusCode === 200) {
        $data = json_decode($response->getBody(), TRUE);
        $this->logger($data);

        return $data;
      }
    } catch (ClientException $e) {
      // Catch the Client Exceptions.
      $this->logger('subscriptionCall API-Client Exception: ' . $e->getMessage(), $statusCode ?? 400);
    }
    catch (\Exception $e) {
      // Catch any other Exceptions.
      $this->logger('subscriptionCall API-Exception: ' . $e->getMessage(), $statusCode ?? 500);
    }

    return '';
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNonSubscribedUsers() {
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    return !empty($users) ? $users : [];
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNonSubscribedWebforms() {
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple(NULL);

    $unSubscribedWebforms = [];
    foreach ($webforms as $webform) {
      if (!empty($webform->getHandlers())) {
        $handlerIds = $webform->getHandlers()->getInstanceIds();
        if (isset($handlerIds[self::WEBFORM_HANDLER_SALESFORCE]) && $webform->get('status') == 'open' && $this->webformMappingSettings->get($webform->id().'_webform_mapping_enable')) {
          $webformSubmissions = $this->entityTypeManager->getStorage('webform_submission')->loadByProperties(['webform_id' => $webform->id()]);
          foreach ($webformSubmissions as $webformSubmission) {
            $data = $webformSubmission->getData();
            if (!(isset($data['sfmc_status']) && $data['sfmc_status'] === 'submitted')) {
              $data['webform_submission_id'] = $webformSubmission->id();
              $data['webform_id'] = $webform->id();
              $unSubscribedWebforms[$webformSubmission->id()] = $data;
            }
          }
        }
      }
    }

    return $unSubscribedWebforms;
  }

  /**
   * @param $webform_id
   * @param $webform_submission_id
   * @return void
   */
  public function updateWebformSubscriptionStatus($webform_id, $webform_submission_id) {

    $webform = Webform::load($webform_id);
    $is_open = WebformSubmissionForm::isOpen($webform);
    if ($is_open === TRUE) {

      $webform_submission = WebformSubmission::load($webform_submission_id);
      $data = $webform_submission->getData();
      $data['sfmc_status'] = 'submitted';
      $webform_submission->setData($data);

      $errors = WebformSubmissionForm::validateWebformSubmission($webform_submission);
      if (empty($errors)) {
        WebformSubmissionForm::submitWebformSubmission($webform_submission);
      }
    }
  }
  /**
   * @return mixed|string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function generateAccessToken() {
    try {
      $accessApi = $this->APISettings->get('is_prod') ? $this->APISettings->get('auth_production_endpoint') : $this->APISettings->get('auth_developer_endpoint');

      $tokenExpiryTimeSpan = $this->APISettings->get('token_expiry_time');
      $lastAccessTokenTime = $this->APISettings->get('last_generated_token_time');
      $lastAccessToken = $this->APISettings->get('current_access_token');

      if (!empty($lastAccessTokenTime) && !empty($lastAccessToken)) {
        $timeDiffSec = time() - $lastAccessTokenTime;
        if ($timeDiffSec < $tokenExpiryTimeSpan) {
          $this->logger('Existing access token utilized.');
          return $lastAccessToken;
        }
      }

      $newAccessTokenTime = time();
      $response = $this->httpClient->request('POST', $accessApi, [
        'form_params' => [
          'grant_type' => $this->grantType,
          'client_id' => $this->clientId,
          'client_secret' => $this->clientSecret,
          'scope' => $this->scope,
          'account_id' => $this->accountId,
        ],
        'headers' => [
          'accept' => self::API_HEADERS_ACCEPT_TYPE
        ]
      ]);

      $statusCode = $response->getStatusCode();
      $this->logger('generateAccessToken: $statusCode-' . $statusCode);

      if ($statusCode === 200) {
        $data = json_decode($response->getBody(), TRUE);
        $this->logger($data);
        $accessToken = $data['access_token'] ?? '';
        $expireIn = $data['expires_in'] ?? '';
        $this->updateAPISettings($accessToken, $newAccessTokenTime, $expireIn);

        return $accessToken;
      }
    } catch (ClientException $e) {
      dd($e->getMessage());
      // Catch the Client Exceptions.
      $this->logger('generateAccessToken API-Client Exception: ' . $e->getMessage(), $statusCode ?? 400);
    }
    catch (\Exception $e) {
      dd($e->getMessage());
      // Catch any other Exceptions.
      $this->logger('generateAccessToken API-Exception: ' . $e->getMessage(), $statusCode ?? 500);
    }

    return '';
  }

  /**
   * @param $access_token
   * @param $access_token_generation_time
   * @param $expire_in
   * @return void
   */
  protected function updateAPISettings($access_token, $access_token_generation_time, $expire_in) {
    $editableAPISettings = $this->configFactory->getEditable('sfmc_connector.api.settings');

    $editableAPISettings->set('last_generated_token_time', $access_token_generation_time);
    $editableAPISettings->set('current_access_token', $access_token);
    $editableAPISettings->set('token_expiry_time', $expire_in);
    $editableAPISettings->save();
  }

  /**
   * @param $message
   * @param $status
   * @param $force
   * @return void
   */
  protected function logger($message, $status = 200, $force = false) {
    if ($status !== 200 || $force || $this->APISettings->get(self::API_DEBUGGER_CONFIG)) {
      !empty($message) && is_array($message) ?
        $this->loggerFactory->get(self::MODULE_CHANNEL_NAME)->debug('<pre><code>' . print_r($message, TRUE) . '</code></pre>')
        : $this->loggerFactory->get(self::MODULE_CHANNEL_NAME)->debug($message);
    }
  }

}
