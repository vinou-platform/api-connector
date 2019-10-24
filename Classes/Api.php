<?php
namespace Vinou\ApiConnector;

use \Vinou\ApiConnector\Session\Session;
use \Vinou\ApiConnector\Tools\Redirect;
use \Vinou\ApiConnector\Tools\Helper;


/**
* Api
*/

class Api {

	protected $authData = [];
	protected $apiUrl = "https://api.vinou.de/service/";
	public $status = 'offline';
	public $dev;
	public $enableLogging;
	public $log = [];

	public function __construct($token = false, $authid = false, $logging = false, $dev = false) {

		if (!$token || !$authid)
			$this->loadYmlSettings();
		else {
			$this->authData['token'] = $token;
			$this->authData['authid'] = $authid;
			$this->enableLogging = $logging;
			$this->dev = $dev;
		}
		$this->validateLogin();
	}

	public function loadYmlSettings() {
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


	public function validateLogin(){

		$authData = Session::getValue('vinou');
		if(!$authData)  {
			$this->login();
		} else {
			$ch = curl_init($this->apiUrl.'check/login');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$headers = [
				'Content-Type: application/json',
				'Origin: '.$_SERVER['SERVER_NAME'],
				'Authorization: Bearer '.$authData['token']
			];

			if (isset($authData['clientToken']))
				array_push($headers, 'Client-Authorization: '.$authData['clientToken']);

			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$requestinfo = curl_getinfo($ch);
			if($httpCode != 200) {
				$this->writeLog('token expired');
				$this->login();
			} else {
				$this->status = 'connected';
				$this->writeLog('token valid');
			}
			return true;
		}
    }

	//request a fresh token based on authid and authtoken
	public function login($cached = true)
	{
		$data_string = json_encode($this->authData);
        $ch = curl_init($this->apiUrl.'login');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_string),
				'Origin: '.$_SERVER['SERVER_NAME']
			]
		);

		$result = json_decode(curl_exec($ch),true);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->writeLog('login and write session');
		if(curl_errno($ch) == 0 && isset($result['token'], $result['refreshToken']))
		{
			$this->writeLog('login succeeded');
			curl_close($ch);
			if ($cached) {
				$this->status = 'connected';
				Session::setValue('vinou',[
					'token' => $result['token'],
					'refreshToken' => $result['refreshToken']
				]);
			}
			return $result;
		}
		$this->writeLog('login failed');
		$this->status = 'offline';
		return false;
	}

	private function curlApiRoute($route, $data = [], $debug = false)
	{
		if (is_null($data) || !is_array($data))
			$data = [];

		if (defined('VINOU_MODE') && VINOU_MODE === 'Shop')
			array_push($data, ['inshop' => true]);

		if (defined('VINOU_MODE') && VINOU_MODE === 'Winelist')
			array_push($data, ['inwinelist' => true]);

		$data_string = json_encode($data);
		$url = $this->apiUrl.$route;
		$this->writeLog('curl route '.$url);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$authData = Session::getValue('vinou');
		$headers = [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string),
			'Origin: '.$_SERVER['SERVER_NAME'],
			'Authorization: Bearer '.$authData['token']
		];

		if (isset($authData['clientToken']))
			array_push($headers, 'Client-Authorization: '.$authData['clientToken']);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		$requestinfo = curl_getinfo($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->writeLog('Status: '.$httpCode);
		switch ($httpCode) {
			case 200:
				curl_close($ch);
				return json_decode($result, true);
				break;
			case 401:
				return [
					'error' => 'unauthorized',
					'info' => $requestinfo,
					'response' => json_decode($result, true)
				];
				break;
			default:
				return [
					'error' => 'recreate token or bad request',
					'info' => $requestinfo,
					'response' => json_decode($result, true)
				];
				break;
		}
		return false;
	}

	public function getWine($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('wines/get',$postData);
		return $result;
	}

	public function getWinesByCategory($postData) {
		$result = $this->curlApiRoute('wines/getByCategory',$postData);
		return $result;
	}

	public function getWinesByType($type) {
		$postData = ['type' => $type];
		$result = $this->curlApiRoute('wines/getByType',$postData);
		return $result['wines'];
	}

	public function getWinesAll($postData = NULL) {
		$postData['language'] = Session::getValue('language') ?? 'de';
		$result = $this->curlApiRoute('wines/getAll',$postData);
		return $result;
	}

	public function getWinesLatest($postData = NULL) {
		$postData['language'] = Session::getValue('language') ?? 'de';
		$postData['orderBy'] = $postData['orderBy'] ?? 'chstamp DESC';
		$postData['max'] = $postData['max'] ?? 9;
		$result = $this->curlApiRoute('wines/getAll',$postData);
		return $result;
	}

	public function searchWine($postData = []) {
		if (!isset($postData['query']))
			return false;

		$postData['language'] = Session::getValue('language') ?? 'de';
		$postData['orderBy'] = $postData['orderBy'] ?? 'chstamp DESC';
		$postData['max'] = $postData['max'] ?? 9;

		$result = $this->curlApiRoute('wines/search',$postData);
		return $this->flatOutput($result, false);
	}

	public function getExpertise($id) {
		$postData = ['id' => $id];
		$result = $this->curlApiRoute('wines/getExpertise',$postData);
		return $result['pdf'];
	}

	public function getWineriesAll($postData = NULL) {
		$result = $this->curlApiRoute('wineries/getAll',$postData);
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
		return $this->flatOutput($result, false);
	}

	public function getCategory($postData = NULL) {
		$result = $this->curlApiRoute('categories/get',$this->detectIdentifier($postData));
		return $result;
	}

	public function getCategoryWithWines($postData = NULL) {
		$result = $this->curlApiRoute('categories/getWithWines',$this->detectIdentifier($postData));
		return $this->flatOutput($result);
	}

	public function getCategoryWines($postData = NULL) {
		$result = $this->curlApiRoute('categories/getWines', $this->detectIdentifier($postData));
		return $this->flatOutput($result, false);
	}

	public function getCategoriesAll($postData = NULL) {
		$result = $this->curlApiRoute('categories/getAll',$postData);
		return $this->flatOutput($result, false, 'categories');
	}

	public function getProductsAll($postData = NULL) {
		$result = $this->curlApiRoute('products/getAll',$postData);
		return $result;
	}

	public function getProduct($postData) {
		$result = $this->curlApiRoute('products/get',$this->detectIdentifier($postData));
		return $this->flatOutput($result);
	}

	public function getClientLogin() {
		$postData = [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'userAgent' => $_SERVER['HTTP_USER_AGENT']
        ];

        if ($this->dev)
        	$postData['ip'] = $this->fetchLokalIP();

		$result = $this->curlApiRoute('clients/login',$postData);
		if (isset($result['token']) && isset($result['refreshToken'])) {
			unset($result['id']);
			unset($result['info']);
			return $result;
		}
		return false;
	}

	public function getBasket($uuid = NULL) {
		$postData = [
			'uuid' => is_null($uuid) ? Session::getValue('basket') : $uuid
		];
		$result = $this->curlApiRoute('baskets/get',$postData);
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
		$result = $this->curlApiRoute('baskets/addItem',$postData);
		$this->initBasket();
		return $result;
	}

	public function editItemInBasket($postData = NULL) {
		$result = $this->curlApiRoute('baskets/editItem',$postData);
		$this->initBasket();
		return $result;;
	}

	public function deleteItemFromBasket($id) {
		$postData['id'] = $id;
		$result = $this->curlApiRoute('baskets/deleteItem',$postData);
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

	public function registerClient($data = NULL) {

        if (is_null($data) || empty($data))
            return ['notsend' => true];

        $errors = [];
        if (count($data) > 0) {
            if ($data['username'] === '')
                array_push($errors, 'username could not be blank');

            if ($data['password'] === '')
                array_push($errors, 'password could not be blank');

            if (strcmp($data['password'],$data['password_repeat']))
                array_push($errors, 'password and repeat did not match');

            if ($data['mail'] === '')
                array_push($errors, 'mail could not be blank');
        }

        if (count($errors) > 0)
            return [
                'error' => 'validation error',
                'details' => implode(', ', $errors),
                'postdata' => $data
            ];

        $data['password'] = md5($this->authData['token'].$data['password']);
        $postData = [
            'data' => $data
        ];

		$result = $this->curlApiRoute('clients/register',$postData);

        if (isset($result['error']))
            return [
                'error' => 'api error',
                'details' => $result['response']['details'],
                'postdata' => $data

            ];

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
		$result = $this->curlApiRoute('clients/login',$postData);

		if (isset($result['error']))
			return ['error' => $result['response']['details']];

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

		$result = $this->curlApiRoute('clients/getPasswordHash',$postData);
		if (isset($result['data']) && $result['data']) {
			$return = $result['data'];
			$return['mail'] = $postData['mail'];
			return $return;
		} else {
			return [
				'error' => 'fetch error',
				'details' => $result['response']['details']
			];
		}
	}

	public function resetPassword($data = NULL) {
		if (is_null($data) || empty($data))
			return false;

		$errors = [];
		$postData = [];
		if (!isset($data['hash']))
			array_push($errors, 'hash not given');
		else
			$postData['lostpassword_hash'] = $data['hash'];

		if (!isset($data['mail']))
			array_push($errors, 'mail not given');
		else
			$postData['mail'] = $data['mail'];

		if (!isset($data['password']) || $data['password'] === '')
			array_push($errors, 'password not given');
		else {
			if ($data['password'] != $data['password_repeat'])
				array_push($errors, 'password repeat does not match');
			else
				$postData['password'] = md5($this->authData['token'].$data['password']);;

		}

		if (count($errors) > 0)
			return [
				'error' => 'validation error',
				'details' => implode(', ',$errors)
			];

		$result = $this->curlApiRoute('clients/resetPassword',$postData);
		if (isset($result['data']) && $result['data']) {
			$return = $result['data'];
			return $return;
		} else {
			return [
				'error' => 'fetch error',
				'details' => $result['response']['details']
			];
		}
	}

	public function getClient($postData = NULL) {
		$result = $this->curlApiRoute('clients/get',$postData);
		$client = $this->flatOutput($result, false);
		Session::setValue('client', $client);
		return $client;
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

		$result = $this->curlApiRoute('clients/edit',$postData);

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
		$result = $this->curlApiRoute('clients/orders/getAll',$postData);
		return $this->flatOutput($result, false);
	}

	public function getOrder($postData = NULL) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['uuid' => $postData];
		$result = $this->curlApiRoute('orders/get',$postData);
		return $this->flatOutput($result, false);
	}

	public function getClientOrder($postData = NULL) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['uuid' => $postData];
		$result = $this->curlApiRoute('clients/orders/get',$postData);
		return $this->flatOutput($result, false);
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

		if (is_array($data) && isset($data[0])) {
			if (is_numeric($data[0]))
				$data['id'] = $data[0];
			else
			 	$data['path_segment'] = $data[0];

			unset($data[0]);
		}
		else
			$data = is_numeric($data) ? ['id' => $data] : ['path_segment' => $data];

		return $data;
	}

	private function flatOutput($data, $retAll = true, $selector = 'data') {
		if ($data[$selector])
			return $data[$selector];

		return $retAll ? $data : false;
	}

}