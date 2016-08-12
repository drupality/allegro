<?php

namespace Drupal\allegro\Util;

class AllegroAPI {

  public $session;
  public $sid;
  public $version;
  public $userId;

  public $userLogin;
  public $userPassword;
  public $webapiKey;
  public $countryCode = 1;

  public $testMode = FALSE;
  public $logged = FALSE;
  public $client;
  public $serviceURL;

  public function __construct() {
    return $this;
  }

  /**
   * Initialise Soap connection.
   *
   * @return AllegroAPI
   */
  public function init() {
    $options = array(
      'connection_timeout' => 120,
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      'trace' => 1,
    );

    if (empty($this->serviceURL)) {
      $this->serviceURL = $this->testMode
        ? 'https://webapi.allegro.pl.webapisandbox.pl/service.php?wsdl'
        : 'https://webapi.allegro.pl/service.php?wsdl';
    }

    $this->client = new \SoapClient($this->serviceURL, $options);

    return $this;
  }

  //////////////////////////////////////////////////////////////////////////////
  //
  // Helper methods for Allegro WebAPI.
  //
  //////////////////////////////////////////////////////////////////////////////

  /**
   * Check if SOAP client is logged to Allegro WebAPI.
   *
   * @return bool
   */
  public function isLogged() {
    return $this->logged;
  }

  /**
   * Tests connection with WebAPI.
   *
   * @param string $login
   *   User login to WebAPI.
   * @param string $password
   *   User login to WebAPI.
   * @param string $apiKey
   *   Key to WebAPI.
   * @param string $countryCode
   *   Code of country to connect through WebAPI.
   * @param bool $testMode
   *   Use WebAPI sandbox instead of production environment.
   *
   * @return bool
   *   TRUE if connection test passed, FALSE otherwise.
   */
  public static function testConnection($login, $password, $apiKey, $countryCode, $testMode = FALSE) {
    $test = new AllegroAPI();
    $test->userLogin = $login;
    $test->userPassword = $password;
    $test->webapiKey = $apiKey;
    $test->countryCode = $countryCode;
    $test->testMode = $testMode;
    $test->login();
    return TRUE;
  }

  /**
   * Get available Allegro Group auction websites.
   *
   * @return array
   *   List of services keyed by country ID.
   */
  public static function getSupportedWebsites() {
    return array(
      1   => 'allegro.pl',
      22  => 'allegro.by',
      34  => 'aukro.bg',
      56  => 'aukro.cz',
      107 => 'allegro.kz',
      168 => 'molotok.ru',
      181 => 'aukro.sk',
      209 => 'aukro.ua',
      228 => 'testwebapi.pl',
    );
  }

  /**
   * Get currencies for Allegro Group auction websites.
   *
   * @return array
   *   List of currencies keyed by country ID.
   */
  public static function getCurrencies() {
    return array(
      1   => 'PLN', // allegro.pl
      22  => 'BYR', // allegro.by
      34  => 'BGN', // aukro.bg
      56  => 'CZK', // aukro.cz
      107 => 'KZT', // allegro.kz
      168 => 'RUB', // molotok.ru
      181 => 'EUR', // aukro.sk
      209 => 'UAH', // aukro.ua
      228 => 'PLN', // testwebapi.pl
    );
  }

  /**
   * Hash password before sending to WebAPI.
   * @param string $plain_text_password
   *   Plain text password.
   *
   * @return string
   *   Enceded with sha246 and base64 password.
   */
  public static function hashPassword($plain_text_password) {
    return base64_encode(hash('sha256', $plain_text_password, TRUE));
  }

  /**
   * Check if hashing functions are available.
   *
   * @return bool
   *   TRUE when hashing is possible, FALSE otherwise.
   */
  public static function isPasswordEncrypted() {
    if ((function_exists('hash') && in_array('sha256', hash_algos()))
      || (function_exists('mhash') && is_int(MHASH_SHA256))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  //////////////////////////////////////////////////////////////////////////////
  //
  // Wrappers for WebAPI functions.
  //
  //////////////////////////////////////////////////////////////////////////////

  public function checkItemDescription($description) {
    $params = array(
      'sessionId' => $this->sid,
      'descriptionContent' => $description,
    );

    return $this->__soapCall('doCheckItemDescription', $params);
  }

  public function checkNewAuction($fields) {
    $params = array(
      'sessionHandle' => $this->sid,
      'fields' => $fields,
    );

    return $this->client->doCheckNewAuctionExt($params);
  }

  /**
   * Comprehensive offer editing.
   *
   * This method allows for comprehensive offer editing both listed and planned
   * to be listed. In order to add elements to the offer or change them, you
   * need to pass data of selected fields of the fieldsToModify structure.
   * To remove elements you need to pass data of selected fields of the
   * fieldsToRemove structure.
   * A list of fields available in the offer can be loaded by using the
   * doGetItemFields method.
   * When bids appear majority of fields is blocked to changes (e.g. description).
   * In such case you can add an additional description in the fid: 25.
   * This method allows for editing the offer and simulating it – the previewOnly
   * field sets the method's mechanics.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1190?lang=en
   *
   * @param int $itemId
   *   User's session identifier received using the doLogin(Enc) method.
   * @param array $fieldsToModify
   *   Identifier of an offer to be changed. The offer may be ongoing or planned
   *   to be listed.
   * @param array $fieldsToRemove
   *   Array of structures containing information on sale form fields that are
   *   to be changed or added.
   * @param bool $previewOnly
   *   Array of field identifiers (fids) that are to be removed.
   *
   * @return object
   *   Following properties are available:
   *   - changedItem: Structure containing information about a changed offer.
   *     - itemId: Offer identifier.
   *     - itemFields: Array of structures containing information about values of fields.
   *     - itemSurcharge: Array of structures of fees charged for a changed offer.
   *       - surchargeDescription: Full description of a fee.
   *       - surchargeAmount: Structure of fee amounts for a changed offer.
   *         - amountValue: Fee amount.
   *         - amountCurrencySign: Mark of a currency in which money is returned.
   */
  public function changeItemFields($itemId, $fieldsToModify = array(), $fieldsToRemove = array(), $previewOnly = FALSE) {
    $params = array(
      'sessionId' => $this->sid,
      'itemId' => $itemId,
      'fieldsToModify' => $fieldsToModify,
      'fieldsToRemove' => $fieldsToRemove,
      'previewOnly' => (int) $previewOnly,
    );

    return $this->client->doChangeItemFields($params);
  }

/**
 * Closing an offer before scheduled time.
 *
 * This method allows for closing the offer (both ongoing and scheduled for listing)
 * of a logged-in user before scheduled time (with or without cancellation of bids).
 *
 * @see http://allegro.pl/webapi/documentation.php/show/id,1122?lang=en
 *
 * @param int $finishItemId
 *   Offer identifier.
 * @param bool $finishCancelAllBids
 *   Information on whether bids are to be cancelled together with closing the offer
 *   (TRUE - yes, FALSE - no; default value is FALSE; only bids placed in the
 *   bidding process are cancelled).
 * @param string $finishCancelReason
 *   Reason for cancellation of bids. Required if finishCancelAllBids = TRUE.
 *
 * @return object
 *   Following properties are available:
 *   - finishValue: Operation result (1 - offer has been ended, 0 - offer has not been ended).
 */
  public function finishItem($finishItemId, $finishCancelAllBids = FALSE, $finishCancelReason = '') {
    $params = array(
      'sessionHandle' => $this->sid,
      'finishItemId' => $finishItemId,
      'finishCancelAllBids' => (int) $finishCancelAllBids,
      'finishCancelReason' => $finishCancelReason,
    );

    return $this->client->doFinishItems($params);
  }

/**
 * Closing many offers before scheduled time.
 *
 * This method allows for closing many offers (both ongoing and scheduled for listing)
 * of a logged-in user before scheduled time (with or without cancellation of bids).
 *
 * @see http://allegro.pl/webapi/documentation.php/show/id,1069?lang=en
 *
 * @param array $finishItemsList
 *   Arrays with structures containing information of offers to be closed (max. 25).
 *
 * @return object
 *   Following properties are available:
 *   - finishItemsSucceed: Array of identifiers of successfully ended offers.
 *   - finishItemsFailed: Arrays of structures containing information on offers
 *       that fail to end.
 *     - finishItemId: Offer identifier.
 *     - finishErrorCode: Error code indicating a reason of the failure.
 *     - finishErrorMessage: Error message describing a reason of the failure.
 */
  public function finishItems(array $finishItemsList) {
    foreach ($finishItemsList as &$finishItem) {
      if (is_numeric($finishItem)) {
        $finishItem = array(
          'finishItemId' => $finishItem,
        );
      }
    }

    $params = array(
      'sessionHandle' => $this->sid,
      'finishItemsList' => $finishItemsList,
    );

    return $this->client->doFinishItems($params);
  }

  /**
   * Loading the category tree.
   *
   * This method allows for loading a full tree of categories available in a
   * selected country.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1101?lang=en
   *
   * @return object
   *   Following properties are available:
   *   - catsList: Array of structures containing information on each category.
   *     - catId: Category identifier.
   *     - catName: Category name.
   *     - catParent: Supracategory identifier (or 0 for main categories).
   *     - catPosition: Position of a category in comparison with other
   *         categories of the same tier (indexed from 0).
   *     - catIsProductCatalogueEnabled: Outdated field (will always return 0).
   *   - verKey: Version key value relevant for a selected country and WebAPI
   *       key provided.
   *   - verStr: Component version of a category’s tree structure relevant for a
   *       WebAPI key provided.
   */
  public function getCatsData() {
    return $this->client->doGetCatsData($this->countryCode, 0, $this->webapiKey);
  }

  /**
   * Loading category counter.
   *
   * This method allows for loading the counter of categories available in the
   * selected country.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1102?lang=en
   *
   * @return object
   *   Following properties are available:
   *   - catsCount: Value of a category's counter.
   *   - verKey: Version key value relevant for a selected country and WebAPI
   *       key provided.
   *   - verStr: Component version of a category’s tree structure relevant for a
   *       WebAPI key provided.
   */
  public function getCatsDataCount() {
    $params = array(
      'countryId' => $this->countryCode,
      'localVersion' => 0,
      'webapiKey' => $this->webapiKey,
    );

    return $this->client->doGetCatsDataCount($params);
  }

  /**
   * Loading tree of categories (in portions).
   *
   * This method allows for loading in portions the full tree of categories
   * available in the indicated country. 50 elements are returned by default.
   * The size of portion can be regulated by the packageElement parameter and
   * the offset parameter allows to control the process of loading next portions
   * of data.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1086?lang=en
   *
   * @param int $offset
   *   Parameter which allows for controlling loading of new data portions
   *     (portion numbers are indexed from 0).
   * @param int $packageElement
   *   Parameter which allows for defining a size of data portion
   *     (scope 1-5000; 5000 by default).
   *
   * @return object
   *   Following properties are available:
   *   - catsList: Array of structures containing information on each category.
   *     - catId: Category identifier.
   *     - catName: Category name.
   *     - catParent: Supracategory identifier (or 0 for main categories).
   *     - catPosition: Position of a category in comparison with other
   *         categories of the same tier (indexed from 0).
   *     - catIsProductCatalogueEnabled: Outdated field (will always return 0).
   *   - verKey: Version key value relevant for a selected country and WebAPI
   *       key provided.
   *   - verStr: Component version of a category's tree structure relevant for a
   *       WebAPI key provided.
   */
  public function getCatsDataLimit($offset = 0, $packageElement = 5000) {
    $params = array(
      'countryId' => $this->countryCode,
      'localVersion' => 0,
      'webapiKey' => $this->webapiKey,
      'offset' => $offset,
      'packageElement' => $packageElement,
    );

    return $this->client->doGetCatsDataLimit($params);
  }

  /**
   * Loading information on many offers.
   *
   * This method allows for loading available information (description,
   * category, photos, parameters, available delivery and payment methods, etc.)
   * on indicated offers.
   *
   * @param array $itemsIdArray
   * @param int $getDesc
   * @param int $getImageUrl
   * @param int $getAttribs
   * @param int $getPostageOptions
   * @param int $getCompanyInfo
   *
   * @return object
   */
  public function getItemsInfo(array $itemsIdArray, $getDesc = 0, $getImageUrl = 0, $getAttribs = 0, $getPostageOptions = 0, $getCompanyInfo = 0) {
    $params = array(
      'sessionHandle' => $this->sid,
      'itemsIdArray' => $itemsIdArray,
      'getDesc' => $getDesc,
      'getImageUrl' => $getImageUrl,
      'getAttribs' => $getAttribs,
      'getPostageOptions' => $getPostageOptions,
      'getCompanyInfo' => $getCompanyInfo,
    );

    return $this->client->doGetItemsInfo($params);
  }

  /**
   * Loading a list of sale form fields.
   *
   * This method allows for loading a list of sale form fields that are
   * available in a given country. Selected fields can be used to e.g. build and
   * fill out the form of listing a new offer using the doNewAuctionExt method.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1110?lang=en
   *
   * @return object
   *   Following properties are available:
   *   - sellFormFields: Array of structures containing information on sale form fields.
   *     - sellFormId: Field identifier.
   *     - sellFormTitle: Field name.
   *     - sellFormCat: Identifier of category to which the field refers (it
   *         also relates to all subcategories of this category). 0 means that
   *         the parameter refers to all the categories.
   *     - sellFormType: Definition of a field type in sale form:
   *         1 - string
   *         2 - integer
   *         3 - float
   *         4 - combobox
   *         5 - radiobutton
   *         6 - checkbox
   *         7 - image (base64Binary)
   *         8 - text (textarea)
   *         9 - datetime (Unix time)
   *         13 - date
   *     - sellFormResType: Indication of a field type where the selected field
   *         value should be provided:
   *         1 - string
   *         2 - integer
   *         3 - float,
   *         7 - image (base64Binary)
   *         9 - datetime (Unix time)
   *         13 - date
   *     - sellFormDefValue: Default field value (for such fields as
   *         combobox/radiobutton/checkbox - on the basis of sellFormOptsValues).
   *     - sellFormOpt: Information on whether the field is obligatory:
   *         1 - obligatory
   *         8 - optional
   *     - sellFormPos: Field position on the list of fields in the sale form.
   *         0 means that sorting is alphabetical.
   *     - sellFormLength: Acceptable size (in characters) of the field value to
   *         be provided.
   *     - sellMinValue: Minimum field value to be provided  (it refers to such
   *         fields as: int, float, combobox, checkbox, date; 0 means there is
   *         no lower limit).
   *     - sellMaxValue: Maximum field value to be provided  (it refers to such
   *         fields as: int, float, combobox, checkbox, date; 0 means there is
   *         no upper limit).
   *     - sellFormDesc: Description of subsequent values that may be set for a
   *         field (it refers to such fields as combobox/radiobutton/checkbox).
   *     - sellFormOptsValues: Indication of specific values (relevant for
   *         descriptions mentioned above) which can be set for a particular
   *         field (it refers to such fields as combobox/radiobutton/checkbox;
   *         for checkbox fields, subsequent values can be summed up).
   *     - sellFormFieldDesc: Detailed field description.
   *     - sellFormParamId: Unique field identifier.
   *     - sellFormParamValues: Unique and fixed parameter value identifiers
   *         (it refers to such fields as combobox/radiobutton/checkbox).
   *     - sellFormParentId: Parent parameter identifier (his sellFormParamId -
   *         only for dependent parameters not being on top of hierarchy. If a
   *         parameter is not a dependent one or if it can be found on top of
   *         hierarchy of dependent parameters, 0 value will be returned in the
   *         field).
   *     - sellFormParentValue: Parent parameter value for which a related
   *         parameter can be used (only for dependent parameters. If a
   *         parameter is not a dependent one no value will be returned in the
   *         field).
   *     - sellFormUnit: Parameter unit (if it has been specified).
   *     - sellFormOptions: Bitmask informing about parameter properties:
   *        1 - dependent
   *        2 - checkbox using the OR operator with a possible selection of one
   *          several or all the values by the seller in the sale form
   *        4 - checkbox using the OR operator with a possible selection of only
   *          one value  by the seller in the sale form
   *        8 - range parameter.
   *        If values 2 and 4 are not set, checkbox is based on the AND operator.
   */
  public function getSellFormFields() {
    $params = array(
      'countryCode' => $this->countryCode,
      'localVersion' => 0,
      'webapiKey' => $this->webapiKey,
    );

    return $this->client->doGetSellFormFieldsExt($params);
  }

  /**
   * Loading fields counter.
   *
   * This method allows for loading the counter of fields available in the
   * selected country.
   *
   * @return int
   *   Number of available fields in country.
   */
  public function getSellFormFieldsCount() {
    return $this->getSellFormFieldsLimit(0, 1)->formFieldsCount;
  }

/**
 * Loading a list of sale form fields (in portions).
 *
 * This method allows for loading (in portions) a list of sale form fields
 * available in the indicated country. Selected fields can be used to e.g. build
 * and fill out the form of listing a new offer using the doNewAuctionExt method.
 * 50 elements are returned by default. The size of portion can be regulated by
 * the packageElement parameter and the offset parameter allows to control the
 * process of loading next portions of data.
 *
 * @see http://allegro.pl/webapi/documentation.php/show/id,1111?lang=en
 *
 * @param int $offset
 *   Parameter which allows for controlling loading of new data bits (bid
 *   numbers are indexed from 0).
 * @param int $packageElement
 *   Parameter which allows for defining a size of data bits (min. 1, default: 50).
 *
 * @return object
 *   @see getSellFormFields()
 */
  public function getSellFormFieldsLimit($offset = 0, $packageElement = 50) {
    $params = array(
      'countryCode' => $this->countryCode,
      'localVersion' => 0,
      'webapiKey' => $this->webapiKey,
      'offset' => $offset,
      'packageElement' => $packageElement,
    );

    return $this->client->doGetSellFormFieldsExtLimit($params);
  }

  /**
   * Get version for specified country.
   *
   * @return int
   */
  public function getVersion() {
    $result = $this->queryAllSysStatus();
    $versionKeys = array();
    foreach ($result->sysCountryStatus->item as $status) {
      $versionKeys[$status->countryId] = $status;
    }

    return $versionKeys[$this->countryCode]->verKey;
  }

  /**
   * Log into WebAPI service.
   *
   * @return object
   *   Allegro client object.
   */
  public function login() {
    $this->init();
    $this->version = $this->getVersion();

    $params = array(
      'userLogin' => $this->userLogin,
      'countryCode' => $this->countryCode,
      'webapiKey' => $this->webapiKey,
      'localVersion' => $this->version,
    );

    if (self::isPasswordEncrypted()) {
      $params['userHashPassword'] = $this->userPassword;
      $this->session = $this->client->doLoginEnc($params);
    }
    else {
      $params['userPassword'] = $this->userPassword;
      $this->session = $this->client->doLogin($params);
    }

    $this->sid = $this->session->sessionHandlePart;
    $this->userId = $this->session->userId;
    $this->logged = TRUE;

    return $this;
  }

  /**
   * Lisitng a new offer.
   *
   * This method allows for listing a new offer. In order to verify the new
   * offer you need to assign it a local identifier (localId) and then check the
   * offer using the doVerifyItem method (localId value is always unique within
   * a given user account). To test whether sale form fields are filled out
   * correctly and/or check costs of listing the offer but without actually
   * doing it, you need to use the doCheckNewAuctionExt method.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1180?lang=en
   *
   * @param array $fields
   *   Array of structures containing information on sale form fields (their
   *   list can be loaded by using the doGetSellFormFieldsExt(Limit) method.
   *   When an identifier of a particular field is passed in the fid field, you
   *   need to send its value in an appropriate for its type fvalue field and
   *   pass empty string of characters [string] - sending 0 (numeric types) or
   *   logical value 'false' [boolean] in other fvalue fields. Pass only
   *   required or desirable fieds.
   *   @see getSellFormFields()
   * @param int $localId
   *   Value of a local identifier that can be additionally assigned to an offer
   *   (scope: 1-9999999999999). Local identifiers are valid until transferring
   *   related offers to archive.
   *
   * @return object
   *   Following properties are available:
   *   - itemId: Listed offer identifier.
   *   - itemInfo: Information on costs related to listing an offer.
   *   - itemIsAllegroStandard: Information whether an offer is marked as
   *       Standard Allegro [PL]/Aukro Plus [CZ]/Super Offer [UA/KZ]
   *       (1 - yes, 0 - no).
   */
  public function newAuction($fields, $localId = 0) {
    $params = array(
      'sessionHandle' => $this->sid,
      'fields' => $fields,
      // 'itemTemplateId' => 0,
      'localId' => $localId,
      // 'itemTemplateCreate' => array(
        // 'itemTemplateOption' => 1,
        // 'itemTemplateName' => 'Test',
      // ),
    );

    return $this->client->doNewAuctionExt($params);
  }

  /**
   * Loading values of all components and version keys.
   *
   * This method allows for loading values of all versioned components and
   * enables preview of version keys for all countries.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1083?lang=en
   *
   * @return object
   *   Following properties are available:
   *   - sysCountryStatus: Array of structures containing information on
   *       components and version keys.
   *     - countryId: Country identifier.
   *     - programVersion: Programme version.
   *     - catsVersion: Version of a category tree structure.
   *     - apiVersion: Allegro WebAPI service version.
   *     - attribVersion: Version of parameters.
   *     - formSellVersion: Version of sale form fields.
   *     - siteVersion: Version of platforms.
   *     - verKey: Version key value.
   */
  public function queryAllSysStatus() {
    $params = array(
      'countryId' => $this->countryCode,
      'webapiKey' => $this->webapiKey,
    );

    return $this->client->doQueryAllSysStatus($params);
  }

  /**
   * Loading value of an indicated component and version key.
   *
   * This method allows for loading the value of one versioned components
   * (category tree and sale form fields) and enables preview of version key for
   * a particular country.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1079?lang=en
   *
   * @param int $sysvar
   *   Component whose value is to be loaded:
   *   3 - category's tree structure
   *   4 - fields of a sale form
   *
   * @return object
   *   Following properties are available:
   *   - info: Version of the selected component.
   *   - verKey: Version key value.
   */
  public function querySysStatus($sysvar = 3) {
    $params = array(
      'sysvar' => $sysvar,
      'countryId' => $this->countryCode,
      'webapiKey' => $this->webapiKey,
    );

    return $this->client->doQuerySysStatus($params);
  }

  /**
   * This method allows to list an offer based on already existing offers.
   *
   * Because of particular mechanism of relisting offers, offers identifiers
   * that are returned on output are in fact identifiers of offers serving as
   * base for listing new offers, therefore these are not identifiers of newly
   * listed offers. In order to check the identifier of a newly listed offer you
   * need to give it a local identifier while calling doSellSomeAgain and use it
   * in the doVerifyItem method.
   *
   * @see http://allegro.pl/webapi/documentation.php/show/id,1130?lang=en
   *
   * @param array $sellItemsArray
   *   Array of identifiers of offers to be relisted (max. 25).
   * @param int $sellStartingTime
   *   Offer listing date (in the Unix time format).
   *   When offers are to be listed right away, pass 0.
   * @param int $sellAuctionDuration
   *   Offer duration time expressed in days (acceptable values: 3, 5, 7, 10, 14
   *   and 21 - last for: Russia, Ukraine, Romania and Serbia).
   * @param int $sellOptions
   *   Parameter allowing to indicate additional actions to be taken after
   *   relisting offer (1 - remove source offers from the proper tab of My Allegro,
   *   2 - send e-mail confirming listing offer, 3 - both).
   * @param array $localIds
   *   Array of local identifiers to be additionally assigned to offers
   *   (range: 1-9999999999999).
   *
   * @return object
   *   Following properties are available:
   *   - itemsSellAgain: Array of structures containing information on relisted offers.
   *     - sellItemId: Identifier of an offer on which basis a new offer has been listed.
   *     - sellItemInfo: Information on costs related to listing an offer.
   *     - sellItemLocalId: Value of an assigned local identifier (to use in doVerifyItem).
   *   - itemsSellFailed: Array of structures containing information on not relisted offers.
   *     - sellItemId: Identifier of an offer on which basis a new offer was supposed to be listed.
   *     - sellFaultCode: Error code indicating a reason of the failure.
   *     - sellFaultString: Error message describing a reason of the failure.
   *   - itemsSellNotFound: Array of identifiers of not relisted offers
   *       (indicated offers were not created by a logged-in user or provided identifiers were incorrect).
   */
  public function sellSomeAgain(array $sellItemsArray, $sellStartingTime = 0, $sellAuctionDuration, $sellOptions, array $localIds = array()) {
    $params = array(
      'sessionHandle' => $this->sid,
      'sellItemsArray' => $sellItemsArray,
      'sellStartingTime' => $sellStartingTime,
      'sellAuctionDuration' => $sellAuctionDuration,
      'sellOptions' => $sellOptions,
      'localIds' => $localIds,
    );

    return $this->client->doSellSomeAgain($params);
  }

/**
 * This method allows for checking the correctness of listing an offer.
 *
 * It checks an offer created by the doNewAuctionExt method when a value of the
 * localId parameter has been passed while calling it from an account of a
 * logged-in user as well as a identifier of a relisted offer (created by using
 * doSellSomeAgain/doSellSomeAgainInShop when a value of the localId parameter
 * has been passed while calling them). localId value is always unique within a
 * given user account.
 *
 * @see http://allegro.pl/webapi/documentation.php/show/id,1075?lang=en
 *
 * @param int $localId
 *  Value of a local identifier that has been passed while listing an offer
 *   using the doNewAuctionExt/doSellSomeAgain/doSellSomeAgainShop method.
 *
 * @return object
 *   Following properties are available:
 *   - itemId: Offer identifier (or -1 when the provided local identifier is incorrect).
 *   - itemListed: Listing offer status (-1 - offer is not and will not be
 *       listed due to technical problems on Allegro or due to providing
 *       incorrect local identifier, 0 - pending to be listed, 1 - offer has
 *       been successfully listed, 2 - offer to be listed: a seller has set the
 *       time of listing in the sale form, 3 - offer to be relisted: relates to
 *       offers listed by using doSellSomeAgain/doSellSomeAgainShop).
 *   - itemStartingTime: Offer start date (in the Unix format; relates to all
 *       offers - planned, to be relisted and those already listed).
 */
  public function verifyItem($localId) {
    $params = array(
      'sessionHandle' => $this->sid,
      'localId' => $localId,
    );

    return $this->client->doVerifyItem($params);
  }

}
