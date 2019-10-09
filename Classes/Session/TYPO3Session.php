<?php
namespace Vinou\ApiConnector\Session;

/**
* TYPO3Session
*/

class TYPO3Session {

	public static $storageKey = 'vinou-api-connector';

	public static function readSessionData($key) {
		$storage = self::getStoreObject();
        $sessionData = $storage->getSessionData( self::$storageKey );
        return isset( $sessionData[$key] ) ? $sessionData[$key] : false;
	}

	public static function writeSessionData($key,$data) {
		$storage = self::getStoreObject();
        $sessionData = $storage->getSessionData( self::$storageKey );
        $sessionData[$key] = $data;
        return $storage->setAndSaveSessionData( self::$storageKey, $sessionData );
	}

	public static function removeSessionData($key) {
		$storage = self::getStoreObject();
		return $storage->setAndSaveSessionData( self::$storageKey, [] );
	}

	public static function getStoreObject() {
		switch (TYPO3_MODE) {
			case 'FE':
				return $GLOBALS['TSFE']->fe_user;
				break;

			case 'BE':
				return $GLOBALS['BE_USER'];
				break;

			default:
				throw new \Exception('TYPO3_MODE not defined');
				exit;
		}
	}

}