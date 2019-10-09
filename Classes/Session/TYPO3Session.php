<?php
namespace Vinou\ApiConnector\Session;

/**
* TYPO3Session
*/

class TYPO3Session {

	public static $storageKey = 'vinou-api-connector';

	public static function readSessionData($key) {
		if (TYPO3_MODE === 'BE')
			return self::readBESessionData($key);

		if ($GLOBALS['TSFE']->loginUser) {
		    return $GLOBALS['TSFE']->fe_user->getKey('user', $key);
		} else {
		    return $GLOBALS['TSFE']->fe_user->getKey('ses', $key);
		}
	}

	public static function writeSessionData($key,$data) {
		if (TYPO3_MODE === 'BE')
			return self::writeBESessionData($key,$data);

		if ($GLOBALS['TSFE']->loginUser) {
			$GLOBALS['TSFE']->fe_user->setKey('user', $key, $data);
		} else {
			$GLOBALS['TSFE']->fe_user->setKey('ses', $key, $data);
		}
		return $GLOBALS['TSFE']->fe_user->storeSessionData();
	}

	public static function removeSessionData($key) {
		if (TYPO3_MODE === 'BE')
			return self::removeBESessionData($key);

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