# Vinou API Connector

The Vinou API Connector is a PHP library that provides the stable functions and utilities that are enabled within the Vinou-Service-API and the Vinou-Public-API. 

### Table of contents

- [Installation](#installation)
- [Usage Example](#usage-example)
	1. [Basic Instantiation](#1-basic-instantiation)
	2. [Instantiation with settings (recommended)](#2-instantiation-with-settings-recommended)
	3. [Improve session handling](#3-improve-session-handling)
	4. [Example function call](#4-example-function-call)
	5. [Prepare Ajax connection](#5-prepare-ajax-connection-content-of-ajaxphp-eg-called-via-httpsyourdomaincomajaxphp)
- [Constants](#constants)
- [Classlist](#classlist)
- [Provider](#provider)


## Installation

```bash
composer install vinou/api-connector
```

## Usage Example

### 1. Basic instantiation

```php
$api = new \Vinou\ApiConnector\Api (
        TOKEN_FOR_INSTANCE,
        AUTHID_FOR_VINOU_CUSTOMER	
      );
```
### 2. Instantiation with settings (recommended)

At first use constants to define path to config.yml
```php
define(VINOU_CONFIG_DIR, '/Path/to/settings.yml');
```

The settings.yml looks like (Be careful keys are case sensitive)
```yaml
vinou:
    token: TOKEN_FOR_INSTANCE
    authid: AUTHID_FOR_VINOU_CUSTOMER
```
than you can instantiate without piping the variables to the API-Object

```php
$api = new \Vinou\ApiConnector\Api();
```

### 3. Improve session handling

By default the generated login token is stored to session. If you want to configure some additional api parameters or if you have some login issues it is better to instantiate a specific Vinou-Session before.
```php
$session = new \Vinou\ApiConnector\Session\Session ();
$session::setValue('language','de');
```
___This session handling is able to detect a TYPO3 session___


### 4. Example function call
```php	
// returns all public wines of your Vinou-Office Account as php array
$api->getWinesAll()
```

### 5. Prepare Ajax connection (content of ajax.php e.g. called via https://your.domain.com/ajax.php)
```php
<?php
        require_once __DIR__ . '/../vendor/autoload.php';

        define('VINOU_ROOT', realpath('./'));
        define('VINOU_MODE', 'Ajax');
        define('VINOU_CONFIG_DIR', '../config/');

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        // INIT SESSION BEFORE ALL THE OTHER STUFF STARTS
        $session = new \Vinou\ApiConnector\Session\Session ();
        $session::setValue('language','de');

        $ajax = new \Vinou\ApiConnector\Ajax ();
        $ajax->run();
?>
```


## Constants

Main options are set by constants. The following constants are avaiable.

|Constant             |Default              |Options              |Description          |
|:--------------------|:--------------------|:--------------------|:--------------------|
|`VINOU_ROOT`        |[DOCROOT]|`/Path/to/application/dir`|Improvement settings for some older environments if document root is not correctly set via server variable|
|`VINOU_CONFIG_DIR`  |not set|`/Path/to/settings.yml`|If set use configuration via settings.yml]
|`VINOU_LOG_DIR`     |logs/|`/Path/to/log/dir/`|Directory for logfiles|
|`VINOU_LOG_LEVEL`   |ERROR|[Loglevel from monolog](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels)||
|`VINOU_DEBUG`       |false|_Boolean_|If set special breakpoints in sdk are written to logfiles|
|`VINOU_MODE`        |not set|`Shop`|load only objects marked with shop flag in Vinou-Office|
|                    ||`Winelist`|load only objects marked as public in Vinou-Office|
|`VINOU_SOURCE`      |Live|`Live`|Use production API https://api.vinou.de|
|                    ||`Staging`|Use staging API https://api.staging.vinou.de (Ready to use features)|
|                    ||`Dev`|Use development API https://api.developmentvinou.de (All new features up from alpha status)| 

## Classlist

|Class                |Description          |
|:--------------------|:--------------------|
|\Vinou\ApiConnector\Api|Main API SDK class|
|\Vinou\ApiConnector\PublicApi|API SDK for small public API|
|\Vinou\ApiConnector\Ajax|Class to handle local json ajax requests to API|
|\Vinou\ApiConnector\FileHandler\Images|File handling to store and cache api images in local application|
|\Vinou\ApiConnector\FileHandler\Pdf|File handling to store and cache api PDFs in local application|
|\Vinou\ApiConnector\Session\Session|Main session class to instantiate, get, set and delete session variables|
|\Vinou\ApiConnector\Session\TYPO3Session|Class that is used to use TYPO3 sesions|
|\Vinou\ApiConnector\Tools\Helper|Some static helper functions e.g. get api urls depending on environment|
|\Vinou\ApiConnector\Tools\Redirect|Helper class to do internal and external redirects|

## Provider

This Library is developed by the Vinou GmbH.

![](http://static.vinou.io/brand/logo/red.svg)

Vinou GmbH<br> 
Mombacher Straße 68<br>
55122 Mainz<br>
E-Mail: [kontakt@vinou.de](mailto:kontakt@vinou.de)<br>
Phone: [+49 6131 6245390](tel:+4961316245390)
