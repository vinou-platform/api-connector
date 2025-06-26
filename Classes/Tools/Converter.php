<?php
namespace Vinou\ApiConnector\Tools;

/**
* Converter
*/

class Converter {

	public static function generateRandomString($length = 48, $charmode = 'both', $numbers = true, $specialChars = false, $unmistakable = false) {
		$chars = '';
		if ($numbers)
			$chars .= $unmistakable ? '23456789' : '0123456789';
		if ($charmode == 'lower' || $charmode == 'both')
			$chars .= $unmistakable ? 'abcdefghijkmnopqrstuvwxyz' : 'abcdefghijklmnopqrstuvwxyz';
		if ($charmode == 'upper' || $charmode == 'both')
			$chars .= $unmistakable ? 'ABCDEFGHJKLMNPRSTUVWXYZ' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if ($specialChars)
			$chars .= '!#%+:=?@';

		$str = '';
		$count = strlen($chars);
		for ($i = 0; $i < $length; $i++)
			$str .= $chars[mt_rand(0, $count - 1)];

		return $str;
	}

	public static function normalizeFileName($fileName) {
		$fileName = strtolower($fileName);
		$fileName = str_replace('ä', 'ae', $fileName);
		$fileName = str_replace('ö', 'oe', $fileName);
		$fileName = str_replace('ü', 'ue', $fileName);
		$fileName = preg_replace('/\s+/', '-', $fileName);
		$fileName = preg_replace('/[^a-z0-9-]/i', '', $fileName);
		$fileName = preg_replace('/-+/', '-', $fileName);
		return $fileName;
	}
}