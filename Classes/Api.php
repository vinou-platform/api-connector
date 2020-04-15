<?php
namespace Vinou\ApiConnector;

use \Vinou\ApiConnector\Session\Session;
use \Vinou\ApiConnector\Tools\Redirect;
use \Vinou\ApiConnector\Tools\Helper;
use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Exception\ClientException;
use \GuzzleHttp\Exception\RequestException;


/**
* Api
*/

class Api {

	/**
	 * @var array $authData	authentication array
	 *	- token: (string) token created in token module in https://app.vinou.de
	 *	- authid: (string) authid from invoice config in settings area in https://app.vinou.de
	 *
	 */
	protected $authData = [];

	/**
	 * @var string $apiUrl url where api is located
	 */
	public $apiUrl;

	/**
	 * @var boolean $connected status of connection
	 */
	public $connected = false;

	/**
	 * @var boolean $dev enable dev mode
	 */
	public $dev = false;

	/**
	 * @var boolean $enableLogging enable logging into log array
	 */
	public $enableLogging;

	/**
	 * @var array $log array to log processes
	 */
	public $log = [];

	/**
	 * @var object $httpClient guzzle object for client
	 */
	public $httpClient = null;

	/**
	 * @param string $token token created in token module in https://app.vinou.de
	 * @param string $authid authid from invoice config in settings area in https://app.vinou.de
	 * @param boolean $logging enable logging if needed
	 * @param boolean $dev enable dev mode
	 */
	public function __construct($token = false, $authid = false, $logging = false, $dev = false) {

		if (!$token || !$authid)
			$this->loadYmlSettings();
		else {
			$this->authData['token'] = $token;
			$this->authData['authid'] = $authid;
			$this->enableLogging = $logging;
			$this->dev = $dev;
		}

		$this->enableLogging = $logging;

		$this->apiUrl = Helper::getApiUrl().'/service/';

		$this->httpClient = new Client([
			'base_uri' => $this->apiUrl
		]);

		$this->validateLogin();
	}

	/**
	 * Load settings from settings.yml in config directory given by constant VINOU_CONFIG_DIR
	 *
	 * @throws \Exception if directory or is misconfigured and token and authid wasn't set
	 */ 
	private function loadYmlSettings() {
		if (!defined('VINOU_CONFIG_DIR'))
			throw new \Exception('no VINOU_CONFIG_DIR constant defined');

		$settingsFile = Helper::getNormDocRoot().VINOU_CONFIG_DIR.'settings.yml';
		if (!is_file($settingsFile))
			throw new \Exception('settings.yml not found');

		else {
			$settings = spyc_load_file($settingsFile);

			if (!isset($settings['vinou']['token']) || !isset($settings['vinou']['authid']))
				throw new \Exception('no vinou settings found in yml');

			else {
				$this->authData = $settings['vinou'];

				if (isset($settings['vinou']['enableLogging']))
					$this->enableLogging = $settings['vinou']['enableLogging'];

				if (isset($settings['vinou']['dev']))
					$this->dev = $settings['vinou']['dev'];
			}
		}
	}

	/**
	 * Checks the vinou tokens set in session and creates new token if old one is expired
	 *
	 * @return boolean returns false if login is expired and token cant be regenerated
	 */ 
	private function validateLogin(){

		$authData = Session::getValue('vinou');
		if(!$authData)  {
			$this->writeLog('no auth session');
			return $this->login();
		} else {
			$this->writeLog('auth session exists');
			$validation = $this->curlApiRoute('check/login');

			if (!$validation) {
				$this->writeLog('token expired');
				return $this->login();
			} else {
				$this->writeLog('token valid');
				$this->connected = true;
			}
		}
		return true;

    }

    /**
     * Creates token with auth credentials and store it to session
     *
     * @param boolean $cached enable token storing into session data
     */
	private function login($cached = true) {

		$result = $this->curlApiRoute(
			'login',
			$this->authData,
			false,
			false
		);

		if (isset($result['token'])) {
			$this->writeLog('login succeeded');
			$this->connected = true;
			if ($cached) {
				Session::setValue('vinou',[
					'token' => $result['token'],
					'refreshToken' => $result['refreshToken']
				]);
				$this->writeLog('token stored in session');
			}
		}
		else {
			$this->writeLog('login failed');
			$this->connected = false;
		}
		
		return true;
	}

	/**
	 * Create API request with postdata using Guzzle http agent
	 *
	 * @param string $route api route to call without leading slash all routes prefixed with service
	 * @param array $data post values
	 * @param boolean $internal enable additional header if client authentification is needed
	 * @param boolean $authorization insert authorization header
	 *
	 * @return array|false $response response body if route status code was 200
	 *
	 */
	private function curlApiRoute($route, $data = [], $internal = false, $authorization = true, $returnErrorResponse = false)
	{
		if (is_null($data) || !is_array($data))
			$data = [];

		if (defined('VINOU_MODE') && VINOU_MODE === 'Shop')
			$data['inshop'] = true;

		if (defined('VINOU_MODE') && VINOU_MODE === 'Winelist')
			$data['inwinelist'] = true;

		$authData = Session::getValue('vinou');
		$headers = [
			'User-Agent' => 'api-connector',
			'Content-Type' => 'application/json',
			'Origin' => ''.$_SERVER['SERVER_NAME']
		];

		if ($authorization) {
			$headers['Authorization'] = 'Bearer '.$authData['token'];

			if (isset($authData['clientToken']) && $internal)
				$headers['Client-Authorization'] = $authData['clientToken'];
		}

		try {
			$response = $this->httpClient->request(
				'POST', 
				$route, 
				[
			    	'headers' => $headers,
			    	'json' => $data
				]
			);

			$this->writeLog([
				'Status' => 200,
				'Route' => $route
			]);
			return json_decode((string)$response->getBody(), true);

		} catch (ClientException $e) {

			$this->writeLog([
				'Status' => $e->getResponse()->getStatusCode(),
				'Route' => $route,
				'Response' => json_decode((string)$e->getResponse()->getBody(), true)
			]);

			if ($returnErrorResponse)
				return json_decode((string)$e->getResponse()->getBody(), true);
			return false;
		}
	}

	public function loadLocalization($countrycode = 'de') {

		$route = '/localization/' . $countrycode;

		try {

			$response = $this->httpClient->get($route);
			$this->writeLog([
				'Status' => 200,
				'Route' => $route
			]);

			return json_decode((string)$response->getBody(), true);

		} catch (ClientException $e) {

			$this->writeLog([
				'Status' => $e->getResponse()->getStatusCode(),
				'Route' => $route,
				'Response' => json_decode((string)$e->getResponse()->getBody(), true)
			]);

			return false;
		}

	}

	public function getWine($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('wines/get', $postData, true);
		return $result;
	}

	public function getWinesByCategory($postData) {
		$result = $this->curlApiRoute('wines/getByCategory', $postData, true);
		return $result;
	}

	public function getWinesByType($type) {
		$postData = ['type' => $type];
		$result = $this->curlApiRoute('wines/getByType', $postData, true);
		return $result['wines'];
	}

	public function getWinesAll($postData = NULL) {
		$postData['language'] = Session::getValue('language') ? Session::getValue('language') : 'de';
		if (!isset($postData['cluster'])) {
			$postData['cluster'] = ['type', 'taste_id', 'vintage', 'grapetypeIds'];
		}
		$result = $this->curlApiRoute('wines/getAll', $postData, true);
		return $result;
	}

	public function getWinesLatest($postData = NULL) {
		$postData['language'] = Session::getValue('language') ? Session::getValue('language') : 'de';
		$postData['orderBy'] = isset($postData['orderBy']) ? $postData['orderBy'] : 'chstamp DESC';
		$postData['max'] = isset($postData['max']) ? (int)$postData['max'] : 9;
		$result = $this->curlApiRoute('wines/getAll', $postData, true);
		return $result;
	}

	public function searchWine($postData = []) {
		if (!isset($postData['query']))
			return false;

		$postData['language'] = Session::getValue('language') ? Session::getValue('language') : 'de';
		$postData['orderBy'] = isset($postData['orderBy']) ? $postData['orderBy'] : 'chstamp DESC';
		$postData['max'] = isset($postData['max']) ? int($postData['max']) : 9;

		$result = $this->curlApiRoute('wines/search', $postData, true);
		return $this->pagedOutput($result, 'wines');
	}

	public function getExpertise($id) {
		$postData = ['id' => $id];
		$result = $this->curlApiRoute('wines/getExpertise',$postData);
		return $result['pdf'];
	}

	public function getWineriesAll($postData = NULL) {
		$result = $this->curlApiRoute('wineries/getAll',$postData);
		if (isset($result['clusters']))
			return [
				'wineries' => $result['data'],
				'clusters' => $result['clusters']
			];
		return $this->flatOutput($result);
	}

	public function getWinery($postData = NULL) {
		$result = $this->curlApiRoute('wineries/get',$this->detectIdentifier($postData));
		return $this->flatOutput($result, false);
	}

	public function getCustomer(){
		$result = $this->curlApiRoute('customers/getMy');
		return $this->flatOutput($result, false);
	}

	public function getAvailablePayments(){
		$result = $this->curlApiRoute('customers/availablePayments');
		return $this->flatOutput($result, false, 'payments');
	}

	public function getCategory($postData = NULL) {
		$result = $this->curlApiRoute('categories/get', $this->detectIdentifier($postData), true);
		return $result;
	}

	public function getCategoryWithWines($postData = NULL) {
		$result = $this->curlApiRoute('categories/getWithWines', $this->detectIdentifier($postData), true);
		return $this->flatOutput($result);
	}

	public function getCategoryWines($postData = NULL) {
		$result = $this->curlApiRoute('categories/getWines', $this->detectIdentifier($postData), true);
		return $this->flatOutput($result, false);
	}

	public function getCategoriesAll($postData = NULL) {
		$result = $this->curlApiRoute('categories/getAll', $postData, true);
		return $this->flatOutput($result, false, 'categories');
	}

	public function getProductsAll($postData = NULL) {
		$result = $this->curlApiRoute('products/getAll', $postData, true);
		return $result;
	}

	public function getProduct($postData) {
		$result = $this->curlApiRoute('products/get', $this->detectIdentifier($postData), true);
		return $this->flatOutput($result);
	}

	public function getBasket($uuid = NULL) {
		$postData = [
			'uuid' => is_null($uuid) ? Session::getValue('basket') : $uuid
		];
		$result = $this->curlApiRoute('baskets/get', $postData, true);
		return $this->flatOutput($result, false);
	}

	public function initBasket() {
		if (Session::getValue('basket')) {
			$basket = $this->getBasket(Session::getValue('basket'));
			if (!$basket) {
				Session::deleteValue('basket');
				return $this->createBasket();
			}

			Session::deleteValue('card');
			if (!empty($basket['basketItems'])){
				Session::setValue('card',$basket['basketItems']);
			}
			return true;
		} else {
			return $this->createBasket();
		}
	}

	public function createBasket() {
		$result = $this->curlApiRoute('baskets/add');
		if (isset($result['data'])) {
			Session::setValue('basket',$result['data']['uuid']);
			return true;
		}
		else
			return false;

	}

	public function addItemToBasket($postData = NULL) {
		$postData['uuid'] = Session::getValue('basket');
		$result = $this->curlApiRoute('baskets/addItem', $postData, true);
		$this->initBasket();
		return $result;
	}

	public function editItemInBasket($postData = NULL) {
		$result = $this->curlApiRoute('baskets/editItem', $postData, true);
		$this->initBasket();
		return $result;;
	}

	public function deleteItemFromBasket($id) {
		$postData['id'] = $id;
		$result = $this->curlApiRoute('baskets/deleteItem', $postData, true);
		$this->initBasket();
		return $result;;
	}

	public function addOrder($order = null) {
		if (is_null($order) || empty($order))
			$order = Session::getValue('order');

		$postData = [
			'data' => $order
		];

		$result = $this->curlApiRoute('orders/add', $postData);

		if (isset($result['targetUrl']) && isset($result['data']['uuid'])) {
			$order = Session::setValue('order_uuid', $result['data']['uuid']);
			Redirect::external($result['targetUrl']);
		}

		return $this->flatOutput($result, false);
	}

	public function findPackage($type,$count) {
		$postData = [
			'type' => $type,
			$type => $count
		];
		$result = $this->curlApiRoute('packaging/find',$postData);
		return $this->flatOutput($result, false);
	}

	public function getBasketSummary() {
		$items = Session::getValue('card');
		if (!$items || empty($items))
			return false;

		$summary = [
			'type' => 'bottles',
			'bottles' => 0
		];

		foreach ($items as $item) {
			$summary['bottles'] = $summary['bottles'] + $item['quantity'];
		}

		Session::deleteValue('summary');
		Session::setValue('summary',$summary);
		return $summary;

	}

	public function getBasketPackage() {
		$summary = $this->getBasketSummary();
		if ($summary['bottles'] > 0) {
			$result = $this->curlApiRoute('packaging/find',$summary);
			Session::deleteValue('package');
			if (isset($result['data'])) {
				Session::setValue('package',$result['data']);
				return $result['data'];
			}
			return false;
		}
		return true;
	}

	public function getAllPackages() {
		$postData = [];
		$result = $this->curlApiRoute('packaging/getAll',$postData);
		return $this->flatOutput($result, false);
	}

	public function finishPaypalPayment($data = NULL) {
		if (is_null($data))
			return false;

		$fieldMapping = [
			'pid' => 'payment_id',
			'paymentId' => 'external_id',
			'PayerID' => 'payer_id',
		];
		$postData = [
			'order_id' => Session::getValue('order_uuid'),
			'payment_type' => 'paypal'
		];

		foreach ($fieldMapping as $dataKey => $targetKey) {
			if (!isset($data[$dataKey]))
				return false;

			$postData[$targetKey] = $data[$dataKey];
		}

		$result = $this->curlApiRoute('orders/checkout/finish',$postData);
		return $this->flatOutput($result, false, 'paypalResult');
	}

	public function cancelPaypalPayment($postData = []) {
		$postData['order_id'] = Session::getValue('order_uuid');

		$result = $this->curlApiRoute('orders/checkout/cancel',$postData);
		return $this->flatOutput($result, false);
	}

	public function getSessionOrder($postData = []) {
		$postData['uuid'] = Session::getValue('order_uuid');
		return $this->getOrder($postData);
	}

	public function registerClient($data = NULL) {

        if (is_null($data) || empty($data))
            return ['notsend' => true];

        $errors = [];
        if (count($data) > 0) {
            if ($data['username'] === '')
                array_push($errors, 'username could not be blank');

            if (!isset($data['password']) || $data['password'] === '')
                array_push($errors, 'password could not be blank');

            if (!isset($data['password_repeat']) || strcmp($data['password'],$data['password_repeat']))
                array_push($errors, 'password and repeat did not match');

            if (!isset($data['mail']) || $data['mail'] === '')
                array_push($errors, 'mail could not be blank');

            if (isset($data['captcha']) && !Helper::validateCaptcha())
            	array_push($errors, 'captcha is not valid');
        }

        if (count($errors) > 0)
            return [
                'error' => 'validation error',
                'details' => implode(', ', $errors),
                'postdata' => $data
            ];

        if (isset($data['captcha']))
        	unset($data['captcha']);

        $data['password'] = md5($this->authData['token'].$data['password']);
        $postData = [
            'data' => $data
        ];

		$result = $this->curlApiRoute('clients/register',$postData, false, true, true);

        if (isset($result['code']) && $result['code'] == 400)
            return [
                'error' => 'api error',
                'details' => $result['details'],
                'postdata' => $data

            ];

        Session::deleteValue('captcha');

        return $this->flatOutput($result, false);
	}

	public function activateClient($postData = NULL) {
		$errors = [];
		if (!isset($postData['mail']))
			array_push($errors, 'no mail set');

		if (!isset($postData['hash']))
			array_push($errors, 'no hash given');
		else
			$postData['lostpassword_hash'] = $postData['hash'];

		if (count($errors) > 0)
			return [
				'error' => 'validation error',
				'details' => implode(', ',$errors)
			];

		$result = $this->curlApiRoute('clients/activate',$postData);
		return isset($result['data']) && $result['data'] ? true : ['error' => 'activation error', 'details' => $result['response']['details']];
	}

	public function clientLogin($postData = NULL) {
		if (is_null($postData) || empty($postData))
			return ['notsend' => true];

		if (!isset($postData['mail']) || !isset($postData['password']))
			return ['error' => 'authData missing'];

		$postData['password'] = md5($this->authData['token'].$postData['password']);
		$result = $this->curlApiRoute('clients/login',$postData, false, true, true);

		if (isset($result['code']) && $result['code'] == 400)
			return [
                'error' => 'api error',
                'details' => $result['details']
            ];

		else {
			$authData = Session::getValue('vinou');
			$authData['clientToken'] = $result['data']['token'];
			Session::setValue('vinou', $authData);

			if (isset($postData['redirect']))
				Redirect::internal($postData['redirect']);
		}

		return $result;
	}

	public function getPasswordHash($postData = NULL) {
		if (is_null($postData) || !isset($postData['mail']))
			return false;

		$result = $this->curlApiRoute('clients/getPasswordHash',$postData, false, true, true);
		if (isset($result['data']) && $result['data']) {
			$return = $result['data'];
			$return['mail'] = $postData['mail'];
			return $return;
		} else {
			return [
				'error' => 'fetch error',
				'details' => $result['details']
			];
		}
	}

	public function validatePasswordHash($postData = NULL) {
		if (is_null($postData) || !isset($postData['mail']) || !isset($postData['hash']))
			return false;

		$postData['lostpassword_hash'] = $postData['hash'];
		unset($postData['hash']);
		$result = $this->curlApiRoute('clients/validatePasswordHash',$postData);
		return $this->flatOutput($result, false);
	}

	public function resetPassword($data = NULL) {
		if (is_null($data) || empty($data))
			return false;

		$validationFields = ['mail', 'hash', 'password', 'password_repeat'];
		foreach ($validationFields as $inputName) {
			if (!isset($data[$inputName]))
				return [
					'error' => 'validation error',
					'details' => $inputName . ' not given'
				];
		}

		if ($data['password'] != $data['password_repeat'])
			return [
				'error' => 'validation error',
				'details' => 'password repeat does not match'
			];

		$postData = [];
		$postData['lostpassword_hash'] = $data['hash'];
		$postData['mail'] = $data['mail'];
		$postData['password'] = md5($this->authData['token'].$data['password']);

		$result = $this->curlApiRoute('clients/resetPassword',$postData, false, true, true);

		if (isset($result['code']) && $result['code'] == 400)
			return [
                'error' => 'reset error',
                'details' => $result['details']
            ];

		return $this->flatOutput($result, false);
	}

	public function getClient($postData = NULL) {
		$result = $this->curlApiRoute('clients/get',$postData, true);
		$client = $this->flatOutput($result, false);
		Session::setValue('client', $client);
		return $client;
	}

	public function checkClientMail($postData = NULL) {
		$result = $this->flatOutput($this->curlApiRoute('clients/exists',$postData), false);
		return is_array($result) && count($result) == 1 ? array_shift($result) : $result;
	}

	public function editClient($data = NULL) {
		if (is_null($data) || empty($data))
			return ['notsend' => true];

		if (isset($data['password'])) {
			if ($data['password'] === '')
				unset($data['password']);
			else
				$data['password'] = md5($this->authData['token'].$data['password']);
		}

		$postData = [
			'data' => $data
		];

		$result = $this->curlApiRoute('clients/edit',$postData, true);

		if (isset($result['error']))
			return ['error' => $result['response']['details']];

		return $this->flatOutput($result, false);
	}

	public function clientLogout($postData = NULL) {
		$authData = Session::getValue('vinou');
		unset($authData['clientToken']);
		Session::setValue('vinou', $authData);
		if (isset($postData['redirect']))
			Redirect::internal($postData['redirect']);
	}

	public function getClientOrders($postData = NULL) {
		$result = $this->curlApiRoute('clients/orders/getAll',$postData, true);
		return $this->flatOutput($result, false);
	}

	public function getOrder($postData = NULL) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['uuid' => $postData];
		$result = $this->curlApiRoute('orders/get', $postData, true);
		return $this->flatOutput($result, false);
	}

	public function getClientOrder($postData = NULL) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['uuid' => $postData];
		$result = $this->curlApiRoute('clients/orders/get', $postData, true);
		return $this->flatOutput($result, false);
	}

	public function getNewsAll($postData = NULL) {
		$result = $this->curlApiRoute('news/getAll',$postData);
		return $this->flatOutput($result, false);
	}

	public function getInternalNewsAll($postData = NULL) {
		$result = $this->curlApiRoute('news/getAll',$postData, true);
		return $this->flatOutput($result, false);
	}

	public function getNews($postData = NULL) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['path_segment' => $postData];
		$result = $this->curlApiRoute('news/get',$postData);
		return $this->flatOutput($result, false);
	}

	public function getTextsAll($postData = NULL) {
		$result = $this->curlApiRoute('texts/getAll',$postData);
		return $this->flatOutput($result, false);
	}

	public function getText($postData = NULL) {
		if (!is_array($postData) && !is_null($postData))
			$postData = is_numeric($postData) ? ['id' => $postData] : ['path_segment' => $postData];

		if (isset($postData['identifier'])) {
			$result = $this->curlApiRoute('texts/getAll',$postData);
			$texts = $this->flatOutput($result, false);
			return isset($texts[0]) ? $texts[0] : false;
		}
		else {
			$result = $this->curlApiRoute('texts/get',$postData);
			return $this->flatOutput($result, false);
		}
	}


	public function fetchLokalIP(){
		$result = $this->curlApiRoute('check/userinfo');
		return $result['ip'];
	}

	private function writeLog($entry) {
		if ($this->enableLogging)
			array_push($this->log,$entry);
	}

	private function detectIdentifier($data) {
		if (is_null($data))
			return $data;

		if (is_numeric($data))
			return ['id' => $data];

		if (is_string($data))
			return ['path_segment' => $data];

		if (is_array($data)) {
			if (isset($data['id']) || isset($data['path_segment']))
				return $data;

			if (isset($data[0])) {
				if (is_numeric($data[0]))
					$data['id'] = $data[0];
				else
				 	$data['path_segment'] = $data[0];

				unset($data[0]);
			}
		}

		return $data;
	}

	private function flatOutput($data, $retAll = true, $selector = 'data') {
		if (isset($data[$selector]))
			return $data[$selector];

		return $retAll ? $data : false;
	}

	private function pagedOutput($result, $dataKey = 'data') {
		if (!isset($result['data']) || empty($result['data']))
			return false;

		return [
			$dataKey => $result['data'],
			'total' => $result['totalCount'],
			'pagination' => $result['pages']
		];
	}

}