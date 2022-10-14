<?php
namespace Vinou\ApiConnector\Tools;

class Redirect {

	public static function external($url) {
		header("Location: ".$url);
		exit;
	}

	public static function internal($route) {
		header("Location: " . Helper::getCurrentHost() . $route);
		exit;
	}

}