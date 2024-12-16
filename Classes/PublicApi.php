<?php
namespace Vinou\ApiConnector;

use \Vinou\ApiConnector\Session\Session;
use \Vinou\ApiConnector\Tools\Helper;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\ClientException;
use \Jaybizzle\CrawlerDetect\CrawlerDetect;
use \Monolog\Logger;
use \Monolog\Handler\RotatingFileHandler;


/**
* PublicApi
*/

class PublicApi {

	/**
	 * @var array $authData	authentication array
	 *	- token: (string) token created in token module in https://app.vinou.de
	 *	- authid: (string) authid from invoice config in settings area in https://app.vinou.de
	 *
	 */
	protected $authData = [];


	/**
	 * @var object $settings settingsservice object
	 */
	public $settingsService = null;

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
	 * @var boolean $crawlerDetect shows if a crawler was detected
	 */
	public $crawler = false;

	/**
	 * @var object $logger monolog object for logs
	 */
	public $logger = null;

	/**
	 * @var string $lastError insert last logged error;
	 */
	public $lastError = null;

	/**
	 * @var boolean $lastStatus last status of api call;
	 */
	public $lastStatus = true;

	public function __construct($logging = false, $dev = false) {

		$this->enableLogging = $logging;
		$this->dev = $dev;

		$this->apiUrl = Helper::getApiUrl().'/';
		$this->httpClient = new Client([
			'base_uri' => $this->apiUrl,
			// 'verify' => Helper::getEnvironment() === 'Production'
		]);

		$this->initLogging();
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
	private function curlApiRoute($route, $data = [])
	{
		if (is_null($data) || !is_array($data))
			$data = [];

		$headers = [
			'User-Agent' => 'api-connector',
			'Content-Type' => 'application/json',
			'Origin' => ''.$_SERVER['SERVER_NAME']
		];

		// prepare logdata
		$logData = [
			'Route' => $route,
			'Data' => $this->cleanUpLogData($data),
			'Pageinfo' => [
				'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
				'GET' => $this->cleanUpLogData($_GET),
				'POST' => $this->cleanUpLogData($_POST),
			]
		];

		try {
			$response = $this->httpClient->request(
				'POST',
				$route,
				[
					'headers' => $headers,
					'json' => $data
				]
			);
			// insert status and response from successful request to logdata and logdata on dev devices
			$logData = array_merge($logData, [
				'Status' => 200,
				'Response' => json_decode((string)$response->getBody(), true)
			]);
			$this->logger->debug('api request', $logData);

			$this->lastStatus = true;

			return json_decode((string)$response->getBody(), true);

		} catch (ClientException $e) {
			$statusCode = $e->getResponse()->getStatusCode();

			// insert status and response from error request
			$logData = array_merge($logData, [
				'Status' => $statusCode,
				'Response' => json_decode((string)$e->getResponse()->getBody(), true)
			]);

			switch ($statusCode) {

				case '401':
					// if only authorization is missing the error is only a warning
					$this->logger->warning('unauthorized', $logData);
					break;

				default:
					// all other errors should be fixed immediatly
					$this->logger->error('error', $logData);
					break;

			}
			$this->lastError = json_decode((string)$e->getResponse()->getBody(), true);
			$this->lastStatus = false;
			switch (Helper::getDebugMode()) {
				case 'inline':
					var_dump($this->lastError);
					break;

				case 'result':
					return $this->lastError;

			}
			return false;
		}
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

	public function getWinery($id) {
		$postData = ['id' => $id];
		$result = $this->curlApiRoute('wineries/get',$postData);
		return $this->flatOutput($result);
	}

	public function getElabel($uuid) {
		$postData = ['uuid' => $uuid];
		$result = $this->curlApiRoute('elabels/get',$postData);
		return $this->flatOutput($result);
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

	public function register($postData = NULL) {
		if (is_null($postData))
			return false;
		$result = $this->curlApiRoute('customers/register',$postData);
		return $this->flatOutput($result, false);
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

	private function initLogging() {

		$logDirName = defined('VINOU_LOG_DIR') ? VINOU_LOG_DIR : 'logs/';

		$logDir = Helper::getNormDocRoot() . $logDirName;

        if (!is_dir($logDir))
            mkdir($logDir, 0777, true);

        $htaccess = $logDir .'/.htaccess';
        if (!is_file($htaccess)) {
            $content = 'Deny from all';
            file_put_contents($htaccess, $content);
        }

		$loglevel = defined('VINOU_LOG_LEVEL') ? Logger::VINOU_LOG_LEVEL : Logger::ERROR;
		if (defined('VINOU_DEBUG') && VINOU_DEBUG)
			$loglevel = Logger::DEBUG;

		$this->logger = new Logger('api');
		$this->logger->pushHandler(new RotatingFileHandler($logDir.'api-connector.log', 30, $loglevel));

		$crawllog = new Logger('crawler');
		$crawllog->pushHandler(new RotatingFileHandler($logDir.'crawler.log', 10, Logger::DEBUG));

		$CrawlerDetect = new CrawlerDetect;
		$this->crawler = $CrawlerDetect->isCrawler();

		if ($this->crawler)
			$crawllog->debug($CrawlerDetect->getMatches());
	}

	private function cleanUpLogData($data) {
		$forbiddenKeys = [
			'password',
			'password_repeat',
			'token',
			'authId'
		];
		foreach ($data as $key => $value) {
			if (in_array($key, $forbiddenKeys))
				unset($data[$key]);
		}
		return $data;
	}

}