<?php
namespace Vinou\ApiConnector\FileHandler;

use \Vinou\ApiConnector\Tools\Helper;

/**
* Images
*/

class Images {

	CONST APIURL = 'https://api.vinou.de';

	public static function getExternalImage($url,$targetFile) {
	    $headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg, image/png, application/octet-stream';
	    $headers[] = 'Connection: Keep-Alive';
	    $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
	    $user_agent = 'php';
	    $process = curl_init($url);
	    curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($process, CURLOPT_HEADER, 0);
	    curl_setopt($process, CURLOPT_USERAGENT, $user_agent); //check here
	    curl_setopt($process, CURLOPT_TIMEOUT, 30);
	    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
	    $rawImage = curl_exec($process);
	    $httpStatus = curl_getinfo($process, CURLINFO_HTTP_CODE);
	    curl_close($process);
	    file_put_contents($targetFile,$rawImage);
	    return $httpStatus;
	}

	public static function storeApiImage($imagesrc, $chstamp = NULL, $localFolder = 'Cache/Images/') {
		$fileName = self::createOptimalFilename(array_values(array_slice(explode('/',$imagesrc), -1))[0]);

		$folder = Helper::getNormDocRoot() . $localFolder;
		if (!is_dir($folder))
			mkdir($folder, 0777, true);
		$localFile = $folder . $fileName;
		$extension = pathinfo($imagesrc, PATHINFO_EXTENSION);
		$changeStamp = strtotime($chstamp);

		$returnArr = [
			'fileName' => $fileName,
			'fileFetched' => FALSE,
			'requestStatus' => 'no request done',
			'absolute' => $localFile,
			'src' => str_replace(Helper::getNormDocRoot(), '/', $localFile),
			'localtime' => is_file($localFile) ? filemtime($localFile) : 0,
			'externaltime' => $changeStamp,
			'recreate' => is_file($localFile) ? $changeStamp > filemtime($localFile) : true
		];

		$fileurl = self::APIURL.$imagesrc;
		$fileurl = preg_replace('/\s+/', '%20', $fileurl);

		if (!$returnArr['recreate'])
			return $returnArr;

		if ($extension == 'svg') {
			$returnArr['src'] = $fileurl;
			return $returnArr;
		}

		$returnArr['requestStatus'] = self::getExternalImage($fileurl,$localFile);
		$returnArr['fileFetched'] = TRUE;

		return $returnArr;
	}

	public static function createOptimalFilename($fileName) {
		$fileSeg = explode('.',$fileName);
		$optimal = self::normalizeFileName($fileSeg[0]).'.'.$fileSeg[1];
		return $optimal;
	}

	public static function normalizeFileName($fileName) {
		$fileName = strtolower($fileName);
		$fileName = str_replace('ä', 'ae', $fileName);
		$fileName = str_replace('ö', 'oe', $fileName);
		$fileName = str_replace('ü', 'ue', $fileName);
		$fileName = preg_replace('/\s+/', '-', $fileName);
		$fileName = preg_replace('#[^a-z0-9-]#i', '', $fileName);
		$fileName = str_replace('--', '-', $fileName);
		return $fileName;
	}

}