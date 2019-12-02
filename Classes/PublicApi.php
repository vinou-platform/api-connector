<?php
namespace Vinou\ApiConnector;

use \Vinou\ApiConnector\Session\Session;
use \Vinou\ApiConnector\Tools\Redirect;
use \Vinou\ApiConnector\Tools\Helper;


/**
* PublicApi
*/

class PublicApi {

	protected $apiUrl = "https://api.vinou.de/";
	public $dev;
	public $enableLogging;
	public $log = [];

	public function __construct($logging = false, $dev = false) {
		$this->enableLogging = $logging;
		$this->dev = $dev;
	}

	private function curlApiRoute($route, $data = [], $debug = false)
	{
		if (is_null($data) || !is_array($data))
			$data = [];

		$data_string = json_encode($data);
		$url = $this->apiUrl.$route;
		$this->writeLog('curl route '.$url);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$headers = [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string),
			'Origin: '.$_SERVER['SERVER_NAME']
		];

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
					'error' => 'bad request',
					'info' => $requestinfo,
					'response' => json_decode($result, true)
				];
				break;
		}
		return false;
	}

	public function getWine($postData) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['path_segment' => $postData];
		$result = $this->curlApiRoute('wines/getPublic',$postData);
		return $this->flatOutput($result, false);
	}

	public function getAllWines($postData = NULL) {
		$result = $this->curlApiRoute('wines/getAll', $postData);
		return $this->pagedOutput($result, 'wines');
	}

	public function getFilters($postData = NULL) {
		$result = $this->curlApiRoute('wines/getFilters', $postData);
		return $this->flatOutput($result, false);
	}

	public function searchWine($postData = []) {
		if (!isset($postData['query']))
			return false;

		$postData['language'] = Session::getValue('language') ?? 'de';
		$postData['orderBy'] = $postData['orderBy'] ?? 'chstamp DESC';
		$postData['max'] = $postData['max'] ?? 9;

		$result = $this->curlApiRoute('wines/search',$postData);
		return $this->pagedOutput($result, 'wines');
	}

	public function searchWinery($postData = []) {
		if (!isset($postData['query']))
			return false;

		$postData['language'] = Session::getValue('language') ?? 'de';
		$postData['orderBy'] = $postData['orderBy'] ?? 'chstamp DESC';
		$postData['max'] = $postData['max'] ?? 9;

		$result = $this->curlApiRoute('wineries/search',$postData);
		return $this->pagedOutput($result, 'wineries');
	}

	public function getWinepass($postData) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['hash' => $postData];
		$result = $this->curlApiRoute('winepass/wines/get',$postData);
		return $this->flatOutput($result, false);
	}

	public function getWinepassWines($postData) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['hash' => $postData];
		$result = $this->curlApiRoute('winepass/wines/getAll',$postData);
		return $this->flatOutput($result, false);
	}

	public function getByCategory($postData) {
		$postData = is_numeric($postData) ? ['id' => $postData] : ['path_segment' => $postData];
		$result = $this->curlApiRoute('wines/getByCategory',$postData);
		return $this->flatOutput($result, false);
	}

	public function getExpertise($id) {
		$postData = ['id' => $id];
		$result = $this->curlApiRoute('wines/getExpertise',$postData);
		return $this->flatOutput($result, false, 'pdf');
	}

	public function getCountries() {
		$result = $this->curlApiRoute('countries/getAll');
		return $this->flatOutput($result, false);
	}

	public function getWineregions($postData = NULL) {
		if (isset($postData['country_code']))
			$result = $this->curlApiRoute('wineregions/getAllByCountryCode', ['country_code' => $postData['country_code']]);
		else
			$result = $this->curlApiRoute('wineregions/getAll');

		return $this->flatOutput($result, false);
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