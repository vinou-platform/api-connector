<?php
namespace Vinou\ApiConnector\Tools;

use \Composer\Autoload\ClassLoader;

/**
 * Api
 */

class Helper {

    const APILIVE = 'https://api.vinou.de';
    const APISANDBOX = 'https://api.sandbox.vinou.de';
    const APIDEV = 'http://api.vinou.frog';

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
        switch ($_SERVER['HTTP_HOST']) {
            case "shop.vinou.frog":
                $apiurl = self::APISANDBOX;
                break;
            case "shop.sandbox.vinou.de":
                $apiurl = self::APISANDBOX;
                break;
            default:
                $apiurl = self::APILIVE;
                break;
        }

        // Override if constant is set
        if ((defined('VINOU_SOURCE') && VINOU_SOURCE === 'Dev'))
            $apiurl = self::APIDEV;

        // Override if constant is set
        if ((defined('VINOU_SOURCE') && VINOU_SOURCE === 'Sandbox'))
            $apiurl = self::APISANDBOX;

        // Override if constant is set
        if ((defined('VINOU_SOURCE') && VINOU_SOURCE === 'Live'))
            $apiurl = self::APILIVE;

        return $apiurl;
    }

    public static function getCurrentHost() {
        $protocol = strpos($_SERVER['SERVER_PROTOCOL'],'https') ? 'https://' : 'http://';
    	return $protocol . $_SERVER['HTTP_HOST'];
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
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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
        }

        return 'data: '.$a.';base64,'.$data;
    }
}
