<?php
namespace Vinou\ApiConnector\FileHandler;

/**
* Pdf
*/

class Pdf {

	CONST APIURL = 'https://api.vinou.de';

	public static function getExternalPDF($url,$targetFile) {
	    set_time_limit(0);
		$fp = fopen ($targetFile, 'w+');
		$process = curl_init(rawurlencode(self::rawUrlEncodeApiPath($url)));
		curl_setopt($process, CURLOPT_TIMEOUT, 50);
		curl_setopt($process, CURLOPT_FILE, $fp);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, true);
		$return = curl_exec($process);
		$httpStatus = curl_getinfo($process, CURLINFO_HTTP_CODE);
		curl_close($process);
		return $httpStatus;
	}

	public static function getExternalPDFBinary($url,$targetFile) {
	    $process = curl_init(self::rawUrlEncodeApiPath($url));
	    curl_setopt($process, CURLOPT_TIMEOUT, 30);
	    curl_setopt($process, CURLOPT_HEADER, 0);
	    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($process, CURLOPT_BINARYTRANSFER,1);
	    $rawPDF = curl_exec($process);
	    $httpStatus = curl_getinfo($process, CURLINFO_HTTP_CODE);
	    curl_close($process);
	    file_put_contents($targetFile,$rawPDF);
	    return $httpStatus;
	}

	public static function storeApiPDF($src,$localFolder,$prefix = '',$chstamp = NULL,$forceDownload = false) {
		$fileName = array_values(array_slice(explode('/',$src), -1))[0];
		$convertedFileName = self::convertFileName($prefix.$fileName);
		$localFile = $localFolder.$convertedFileName;

		$chdate = new \DateTime($chstamp);
		$changeStamp = $chdate->getTimestamp();
		$returnArr = [
			'fileName' => $convertedFileName,
			'fileFetched' => FALSE,
			'requestStatus' => 'no request done'
		];

		if(!file_exists($localFile)){
			// $result = self::getExternalPDF(self::APIURL.$src,$localFile);
			$returnArr['requestStatus'] = self::getExternalPDFBinary(self::APIURL.$src,$localFile);
			$returnArr['fileFetched'] = TRUE;
		} else if (!is_null($chstamp) && $changeStamp > filemtime($localFile)) {
			// $result = self::getExternalPDF(self::APIURL.$src,$localFile);
			$returnArr['requestStatus'] = self::getExternalPDFBinary(self::APIURL.$src,$localFile);
			$returnArr['fileFetched'] = TRUE;
		} else if ($forceDownload) {
			$returnArr['requestStatus'] = self::getExternalPDFBinary(self::APIURL.$src,$localFile);
			$returnArr['fileFetched'] = TRUE;
		}

		return $returnArr;
	}

	public static function rawUrlEncodeApiPath($url) {
		$urlExplode = explode('://', $url);
	    $url = $urlExplode[0].'://'.implode('/', array_map('rawurlencode', explode('/', $urlExplode[1])));
	    return $url;
	}

	public static function convertFileName($fileName) {
		$fileName = strtolower($fileName);
		$fileName = str_replace(' ', '_', $fileName);
		$fileName = str_replace("ä", "ae", $fileName);
		$fileName = str_replace("ü", "ue", $fileName);
		$fileName = str_replace("ö", "oe", $fileName);
		$fileName = str_replace("Ä", "Ae", $fileName);
		$fileName = str_replace("Ü", "Ue", $fileName);
		$fileName = str_replace("Ö", "Oe", $fileName);
		$fileName = str_replace("ß", "ss", $fileName);
		$fileName = str_replace("´", "", $fileName);
		return $fileName;
	}

}