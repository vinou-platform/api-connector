<?php
namespace Vinou\ApiConnector\Tools;

/**
* Converter
*/

class Converter {

	public static function generateUuidHash($lenght = 36) {
		// uniqid gives 13 chars, but you could adjust it to your needs.
		if (function_exists('random_bytes')) {
			$bytes = random_bytes(ceil($lenght / 2));
		} elseif (function_exists('openssl_random_pseudo_bytes')) {
			$bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
		} else {
			throw new \Exception('no cryptographically secure random function available');
		}
		return substr(bin2hex($bytes), 0, $lenght);
	}

	public static function generateUuidNamespace() {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),

			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	public static function generateUuid() {
		$namespace = self::generateUuidNamespace();
		$name = self::generateUuidHash();

		if (!self::is_valid_uuid($namespace))
			return false;

		// Get hexadecimal components of namespace
		$nhex = str_replace(array('-', '{', '}'), '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0; $i < strlen($nhex); $i += 2) {
		  $nstr .= chr(hexdec($nhex[$i].$nhex[$i + 1]));
		}

		// Calculate hash value
		$hash = sha1($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',

		  // 32 bits for "time_low"
		  substr($hash, 0, 8),

		  // 16 bits for "time_mid"
		  substr($hash, 8, 4),

		  // 16 bits for "time_hi_and_version",
		  // four most significant bits holds version number 5
		  (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,

		  // 16 bits, 8 bits for "clk_seq_hi_res",
		  // 8 bits for "clk_seq_low",
		  // two most significant bits holds zero and one for variant DCE1.1
		  (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,

		  // 48 bits for "node"
		  substr($hash, 20, 12)
		);
	}

	public static function is_valid_uuid($uuid) {
		return preg_match(
			'/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' .
			'[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i',
			$uuid
		) === 1;
	}

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

	public static function generateHash($opts = [], $checkUnique = null) {
		$defaults = [
			'length' => 8,
			'charmode' => 'upper',
			'numbers' => true,
			'specialChars' => false,
			'unmistakable' => true
		];
		if (!is_array($opts))
			$opts = ['length' => $opts];
		$opts = array_merge($defaults, $opts);

		// Maximum retries to find a unique hash.
		$max = 8;
		for ($i = 0; $i <= $max; $i++) {
			$hash = call_user_func_array([get_called_class(), 'generateRandomString'], $opts);

			// Return generated hash if it not already exists.
			if (!$checkUnique || call_user_func($checkUnique, $hash))
				return $hash;
			if ($i == $max) {
				throw new \Exception('Hash could not be generated. Maximum count of collisions reached');
				break;
			}
		}

	}
}