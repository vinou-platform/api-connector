<?php
namespace Vinou\ApiConnector\Tools;

use \Vinou\ApiConnector\Session\Session;
use \Composer\Autoload\ClassLoader;

/**
 * Api
 */

class Helper {

    public static $urls = [
        'Local' => 'https://api.vinou.site',
        'Development' => 'https://api.development.vinou.de',
		'Sandbox' => 'https://api.staging.vinou.de',
        'Staging' => 'https://api.staging.vinou.de',
        'Production' => 'https://api.vinou.de',
    ];

    private static $normDocRoot = NULL;

    public static function getNormDocRoot(){
        if(self::$normDocRoot == NULL){
            $str = defined('VINOU_ROOT') ? VINOU_ROOT : $_SERVER['DOCUMENT_ROOT'];
            $strLength = strlen($str);
            if($strLength > 0){
                if($str[0] != '/'){
                    $str = '/'.$str;
                    $strLength++;
                }
                if($str[$strLength-1] != '/') {
                    $str = $str.'/';
                }
            }
            self::$normDocRoot = $str;
        }
        return self::$normDocRoot;
    }

    public static function getApiUrl() {
        return self::$urls[self::getEnvironment()];
    }

	public static function getEnvironment() {
		$env = 'Production';

		if (defined('VINOU_SOURCE') && isset(self::$urls[VINOU_SOURCE]))
			$env = VINOU_SOURCE;

		if (getenv('VINOU_SOURCE') && isset(self::$urls[getenv('VINOU_SOURCE')]))
			$env = getenv('VINOU_SOURCE');

		return $env;
	}

	public static function getDebugMode() {
		$mode = false;
		if (defined('VINOU_DEBUG') || getenv('VINOU_DEBUG')) {
			$conf = defined('VINOU_DEBUG') ? VINOU_DEBUG : getenv('VINOU_DEBUG');

			switch ($conf) {
				case 'result':
				case 2:
					$mode = 'result';
					break;


				case 'inline':
				case 1:
					$mode = 'inline';
					break;
			}
		}

		return $mode;
	}

    public static function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }

    public static function fetchProtocol() {
        return self::isHTTPS() ? 'https://' : 'http://';
    }

    public static function getCurrentHost() {
    	return self::fetchProtocol() . $_SERVER['HTTP_HOST'];
    }

    public static function getClassPath($class = "\Composer\Autoload\ClassLoader")
    {
        $reflector = new \ReflectionClass($class);
        $ClassPath = $reflector->getFileName();
        if($ClassPath && is_file($ClassPath)) {
            $segments = explode('/',$ClassPath);
            array_pop($segments);
            return implode('/',$segments);
        }
        throw new \RuntimeException('Unable to detect vendor path.');
    }

    public static function findKeyInArray($keyArray,$array) {
        $searchArray = $array;
        foreach ($keyArray as $key) {
            if (isset($searchArray[$key])) {
                $searchArray = $searchArray[$key];
            } else {
                return false;
            }
        }
        return $searchArray;
    }

    public static function curl_get_contents($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public static function imageToBase64($url){
        $data = base64_encode(self::curl_get_contents($url));
        $mime_types = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'odt' => 'application/vnd.oasis.opendocument.text ',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'gif' => 'image/gif',
            'jpg' => 'image/jpg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'bmp' => 'image/bmp'
        );
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        if (array_key_exists($ext, $mime_types)) {
            $a = $mime_types[$ext];
			return 'data: '.$a.';base64,'.$data;
        }

		return false;

    }

	public static function validateCaptcha($dynamicCaptchaInput = false) {
		if (!isset($_POST['captcha']))
			return false;

		if ($dynamicCaptchaInput) {
			if (!isset($_POST[$_POST['captcha']]))
				return false;
			$phrase = $_POST[$_POST['captcha']];
		}
		else
			$phrase = $_POST['captcha'];

		$sessionPhrase = Session::getValue('captcha');
		return $phrase === (string)$sessionPhrase;
	}
}
