<?php
namespace Vinou\ApiConnector\Session;

/**
* TYPO3Session
*/

class TYPO3Session {

	public static $storageKey = 'vinou-api-connector';

	public static function checkTYPO3Mode($mode) {
		if (!defined('TYPO3_MODE')) {
			define('TYPO3_MODE', false);
		}

		return TYPO3_MODE === $mode;
	}

	public static function readSessionData($key) {
		if (self::checkTYPO3Mode('BE'))
			return self::readBESessionData($key);

		if ($GLOBALS['TSFE']->loginUser) {
		    return $GLOBALS['TSFE']->fe_user->getKey('user', $key);
		} else {
		    return $GLOBALS['TSFE']->fe_user->getKey('ses', $key);
		}
	}

	public static function writeSessionData($key,$data) {
		if (self::checkTYPO3Mode('BE'))
			return self::writeBESessionData($key,$data);

		if ($GLOBALS['TSFE']->loginUser) {
			$GLOBALS['TSFE']->fe_user->setKey('user', $key, $data);
		} else {
			$GLOBALS['TSFE']->fe_user->setKey('ses', $key, $data);
		}
		return $GLOBALS['TSFE']->fe_user->storeSessionData();
	}

	public static function removeSessionData($key) {
		if (self::checkTYPO3Mode('BE'))
			return self::removeBESessionData($key);

		$GLOBALS['TSFE']->fe_user->setAndSaveSessionData($key, null);
		unset($GLOBALS['TSFE']->fe_user->sesData[$key]);
		return $GLOBALS['TSFE']->fe_user->storeSessionData();
	}


	public static function readBESessionData($key) {
        $sessionData = $GLOBALS['BE_USER']->getSessionData( self::$storageKey );
        return isset( $sessionData[$key] ) ? $sessionData[$key] : false;
	}

	public static function writeBESessionData($key,$data) {
        $sessionData = $GLOBALS['BE_USER']->getSessionData( self::$storageKey );
        $sessionData[$key] = $data;
        return $GLOBALS['BE_USER']->setAndSaveSessionData( self::$storageKey, $sessionData );
	}

	public static function removeBESessionData($key) {
		return $GLOBALS['BE_USER']->setAndSaveSessionData( self::$storageKey, [] );
	}

}