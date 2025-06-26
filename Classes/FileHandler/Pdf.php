<?php
namespace Vinou\ApiConnector\FileHandler;

use \Vinou\ApiConnector\Tools\Helper;

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
	    curl_setopt($process, CURLOPT_HEADER, false);
	    curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($process, CURLOPT_BINARYTRANSFER,1);
	    $rawPDF = curl_exec($process);
	    $httpStatus = curl_getinfo($process, CURLINFO_HTTP_CODE);
	    curl_close($process);
	    file_put_contents($targetFile,$rawPDF);
	    return $httpStatus;
	}

	public static function storeApiPDF($src, $chstamp = NULL, $localFolder = 'Cache/Pdf/', $prefix = '', $forceDownload = false) {

		$fileName = array_values(array_slice(explode('/',$src), -1))[0];
		if (!is_null($chstamp))
			$fileName = strtotime($chstamp) . '-' . $fileName;
		$convertedFileName = self::convertFileName($prefix.$fileName);

		if (substr($localFolder, 0, 1)  === '/')
			$folder = $localFolder;
		else
			$folder = Helper::getNormDocRoot() . $localFolder;

		if (!is_dir($folder))
			mkdir($folder, 0777, true);

		$localFile = $folder . $convertedFileName;

		$chdate = new \DateTime($chstamp);
		$changeStamp = $chdate->getTimestamp();
		$returnArr = [
			'src' => str_replace(Helper::getNormDocRoot(), '/', $localFile),
			'fileName' => $convertedFileName,
			'fileFetched' => FALSE,
			'requestStatus' => 'no request done'
		];

		if (
			!file_exists($localFile)
			|| $forceDownload
			|| (!is_null($chstamp) && $changeStamp > filemtime($localFile))
		) {
			$returnArr['requestStatus'] = self::getExternalPDFBinary(Helper::getApiUrl().$src,$localFile);
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