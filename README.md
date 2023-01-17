# Salesforce(sfmc) Connector

- This module helps to connect to the salesforce(sfmc) and push the consumer profile and subscription preference (OptIns/OptOuts) SFMC endpoint.
- This module provides option to configure the sfmc API settings
- Subscription feature can be enabled on user/webform entities.
- This also provides the field mapping between sfmc and user/webform entity fields.

------------

## Instructions:


- Enable the `sfmc_connector` module.
  - Enable `webform` module if you need to enable a subscription on webform.

------------

### Salesforce Settings

- Goto `admin/config/sfmc_connector/api/settings` page to configure the salesforce settings.
- This is further separated into two section/tabs - `Access Token` and `Subscription`.
- **_Access Token:_**
  * Grant type
  * Client id
  * Client secret
  * Scope Default: `data_extensions_read data_extensions_write`
  * Account id
  * Developer Auth Endpoint
  * Production Auth Endpoint
  * `Enable Production Endpoint?` if enabled, it will use `Production Auth Endpoint` (Enable this only in production environment).
  * `Enable API debugger?` option will be useful for debugging purpose. It will log the details API response details in watchdog.
- **_Subscription:_**
  * Source id
  * Subscription id
  * Developer Subscription Endpoint
  * Production Subscription Endpoint


------------

### Field Mapping settings

- Goto `admin/config/sfmc_connector/fields_mapping/user/settings` to access user or webform field mapping.
- This is further separated into two section/tabs which is `User Fields` and `Webform Fields`.
- **_User Fields:_**
  * `Enable User Entity for Subscription?` If checked, user entity fields will be mapped and available for salesforce subscription.
  * `Subscription Id` If left empty, it will take the subscription id from API Settings.
  * It will list down all user entity fields which can be mapped with salesforce(sfmc) subscription attributes.
- **_Webform Fields:_**
  * `Enable [webform id] webform for Subscription?` If checked, webform fields will be mapped and available for salesforce subscription.
  * `Subscription Id` If left empty, it will take the subscription id from API Settings.
  * It will list down all webforms with respective fields.

------------

### Salesforce Batch Settings

- Goto `admin/config/sfmc_connector/sfmc_batch/settings` to access backlog processor for user & webform subscriptions.


