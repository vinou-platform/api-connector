<?php
namespace Vinou\ApiConnector;

use Vinou\ApiConnector\Session\Session;

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

	public function __construct($token = '', $authid = '', $logging = false, $dev = false) {
		$this->authData['token'] = $token;
		$this->authData['authid'] = $authid;
		$this->enableLogging = $logging;
		$this->dev = $dev;
		$this->validateLogin();
	}


	public function validateLogin(){

		if(!Session::getValue('vinou'))  {
			$this->login();
		} else {
			$ch = curl_init($this->apiUrl.'check/login');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER,
				[
					'Content-Type: application/json',
					'Origin: '.$_SERVER['SERVER_NAME'],
					'Authorization: Bearer '.Session::getValue('vinou')['token']
				]
			);
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
		if (is_null($data))
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
		curl_setopt($ch, CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_string),
				'Origin: '.$_SERVER['SERVER_NAME'],
				'Authorization: Bearer '.Session::getValue('vinou')['token']
			]
		);
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
		if (isset($result['data']))
			return $result['data'];
		else
			return false;
	}

	public function getExpertise($id) {
		$postData = ['id' => $id];
		$result = $this->curlApiRoute('wines/getExpertise',$postData);
		return $result['pdf'];
	}

	public function getWineriesAll($postData = NULL) {
		$result = $this->curlApiRoute('wineries/getAll',$postData);
		return isset($result['data']) ? $result['data'] : $result;
	}

	public function getWinery($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('wineries/get',$postData);
		return isset($result['data']) ? $result['data'] : $result;
	}

	public function getCustomer(){
		$result = $this->curlApiRoute('customers/getMy');
		return $result['data'];
	}

	public function getAvailablePayments(){
		$result = $this->curlApiRoute('customers/availablePayments');
		return $result;
	}

	public function getCategory($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('categories/get',$postData);
		return $result;
	}

	public function getCategoryWithWines($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('categories/getWithWines',$postData);
		return $result;
	}

	public function getCategoriesAll() {
		$result = $this->curlApiRoute('categories/getAll');
		return $result['categories'];
	}

	public function getProductsAll($postData = NULL) {
		$result = $this->curlApiRoute('products/getAll',$postData);
		return $result;
	}

	public function getProduct($input) {
		$postData = is_numeric($input) ? ['id' => $input] : ['path_segment' => $input];
		$result = $this->curlApiRoute('products/get',$postData);
		return $result;
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
		return isset($result['data']) ? $result['data'] : false;
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
		return $result;
	}

	public function findPackage($type,$count) {
		$postData = [
			'type' => $type,
			$type => $count
		];
		$result = $this->curlApiRoute('packaging/find',$postData);
		return $result['data'];
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
		return $result['data'];
	}

	public function fetchLokalIP(){
		$result = $this->curlApiRoute('check/userinfo');
		return $result['ip'];
	}

	private function writeLog($entry) {
		if ($this->enableLogging)
			array_push($this->log,$entry);
	}
}