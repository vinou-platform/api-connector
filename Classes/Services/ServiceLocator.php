<?php
namespace Vinou\ApiConnector\Services;

/**
 * Singleton Vinou/ApiConnector/Services/ServiceLocator to get access to specific services, e.g.
 * "Settings" or "Database".
 */
class ServiceLocator {

	private static $services = [];

	private function __construct() {}

	public static function register($name, $class, callable $dependencies = null) {
		self::$services[$name] = [
			'class' => $class,
			'dependencies' => $dependencies
		];
	}

	public static function &get($name) {
		$service = &self::$services[$name];
		if (is_array($service)) {
			$class = new \ReflectionClass($service['class']);
			$dependencies = $service['dependencies'] ? call_user_func($service['dependencies']) : [];
			$service = $class->newInstanceArgs($dependencies);
			self::$services[$name] = &$service;
		}
		return $service;
	}
}

ServiceLocator::register('Settings', \Vinou\ApiConnector\Services\Settings::class);
ServiceLocator::register('Translator', \Vinou\Translations\Utilities\Translation::class);
