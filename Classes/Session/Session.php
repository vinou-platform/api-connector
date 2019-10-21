<?php
namespace Vinou\ApiConnector\Session;

use \Vinou\ApiConnector\Session\TYPO3Session;
use \Vinou\ApiConnector\Tools\Converter;
use \Vinou\ApiConnector\Tools\Helper;

/**
* Session
*/

class Session {

    public function __construct() {
        self::checkTempFolder();
        self::start();
    }


    //this method should be called at the start of the program
    public static function start() {
        ob_start();
        session_start();

        // DETECT IF SESSION EXISTS
        if (!isset($_SESSION['id'])) {
            $_SESSION['id'] = Converter::generateRandomString();
            self::setValue('start',strftime('%d.%m.%Y %H:%M:%S',time()));
        }
    }

    public static function checkTempFolder($parent = null) {
        $tmpDir = is_null($parent) ? Helper::getNormDocRoot().'tmp' : $parent .'/tmp';
        if (!is_dir($tmpDir))
            mkdir($tmpDir, 0755, true);

        ini_set('session.save_path', $tmpDir);
    }

    public static function setValue($key,$value) {

        switch (self::detectEnvironment()) {

            case 'TYPO3':
                return TYPO3Session::writeSessionData($key,$value);
                break;

            default:
                $_SESSION[$key] = $value;
        }
        return true;
    }

    public static function getValue($key) {
        switch (self::detectEnvironment()) {

            case 'TYPO3':
                return TYPO3Session::readSessionData($key);
                break;

            default:
                if (isset($_SESSION[$key]))
                    return $_SESSION[$key];
        }
        return false;
    }

    public static function deleteValue($key) {

        switch (self::detectEnvironment()) {

            case 'TYPO3':
                TYPO3Session::removeSessionData($key);
                break;

            default:
                if (isset($_SESSION[$key]))
                    unset($_SESSION[$key]);
        }
        return true;
    }

    public static function detectEnvironment() {
        if (defined('VINOU_MODE') && in_array(VINOU_MODE, ['Ajax', 'cli']))
            return 'PHP-Ajax';

        if (defined('TYPO3_MODE'))
            return 'TYPO3';

        return 'PHP';
    }

    public static function is_session_started() {
        if ( php_sapi_name() !== 'cli' ) {
            if ( version_compare(phpversion(), '5.4.0', '>=') ) {
                return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
            } else {
                return session_id() === '' ? FALSE : TRUE;
            }
        }
        return FALSE;
    }

}