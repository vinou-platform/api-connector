<?php
namespace Vinou\ApiConnector\Tools;

/**
 * Vinou/ApiConnector/Tools/Settings
 */
class Settings {

	private $settings = [
		// Defaults.
	];

	public function get($key) {
		return isset($this->settings[$key]) ? $this->settings[$key] : null;
	}

	public function getAll() {
		return $this->settings;
	}

	public function set($key, $value) {
		$oldValue = $this->get($key);
		$this->settings[$key] = $value;
		return $oldValue;
	}
}
?>