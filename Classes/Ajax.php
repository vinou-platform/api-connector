<?php
namespace Vinou\ApiConnector;

use \Vinou\ApiConnector\Api;
use \Vinou\ApiConnector\Session\Session;

/**
* Ajax
*/

class Ajax {

	protected $api = null;
	protected $errors = [];
	protected $output = false;
	protected $data = [];

	public function __construct() {

		$this->api = new Api();

		if (!$this->api->connected) {
			array_push($this->errors, 'could not create api connection');
		}
	}

	public function run() {
		$this->loadInput();
		$this->handleActions();
		$this->renderOutput();
	}

	private function loadInput() {
		$this->data = array_merge($_POST, (array)json_decode(trim(file_get_contents('php://input')), true));
	}

	private function handleActions() {
		if (empty($this->data) || !isset($this->data['action'])) {
			array_push($this->errors, 'no action defined');
			return false;
		}

	    $action = $this->data['action'];
	    unset($this->data['action']);
        switch ($action) {
            case 'get':
                $this->output = $this->api->getBasket();
                break;

            case 'addItem':
                $this->output = $this->api->addItemToBasket($this->data);
                break;

            case 'editItem':
                $this->output = $this->api->editItemInBasket($this->data);
                break;

            case 'deleteItem':
                $this->output = $this->api->deleteItemFromBasket($this->data['id']);
                break;

            case 'findPackage':
                $this->output = $this->api->getBasketPackage();
                // force return to prevent error handling
                return;

            case 'findCampaign':
                $this->output = $this->api->findCampaign($this->data);
                break;

            case 'loadCampaign':
            	$this->output = $this->api->findCampaign($this->data);
            	if ($this->output)
            		Session::setValue('campaign', $this->output);
            	break;

            default:
                array_push($this->errors, 'action could not be resolved');
                break;
        }

        if (!$this->output)
        	array_push($this->errors, 'no result created');
	}

	private function renderOutput() {
	    if (count($this->errors) > 0)
	        $this->sendError($this->errors);
	    else
	    	$this->sendResult();

	}

	private function sendError($data) {
		header('HTTP/1.0 400 Bad Request');
        echo json_encode($data);
        exit();
	}

	private function sendResult() {
	    header('HTTP/1.1 200 OK');
	    echo json_encode($this->output);
	    exit();
	}


}