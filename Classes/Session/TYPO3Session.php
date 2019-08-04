<?php
namespace Vinou\ApiConnector\Session;

/**
* TYPO3Session
*/

class TYPO3Session {

	public static function readSessionData($key) {
		if ($GLOBALS['TSFE']->loginUser) {
		    return $GLOBALS['TSFE']->fe_user->getKey('user', $key);
		} else {
		    return $GLOBALS['TSFE']->fe_user->getKey('ses', $key);
		}
	}

	public static function writeSessionData($key,$data) {
		if ($GLOBALS['TSFE']->loginUser) {
			$GLOBALS['TSFE']->fe_user->setKey('user', $key, $data);
		} else {
			$GLOBALS['TSFE']->fe_user->setKey('ses', $key, $data);
		}
		return $GLOBALS['TSFE']->fe_user->storeSessionData();
	}

	public static function removeSessionData($key) {
		unset($GLOBALS['TSFE']->fe_user->sesData[$key]);
		return $GLOBALS['TSFE']->fe_user->storeSessionData();
	}

}