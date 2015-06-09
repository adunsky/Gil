<?php
/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
 
include_once "google-api-php-client-master/examples/templates/base.php";
session_start();

require_once realpath(dirname(__FILE__) . '/google-api-php-client-master/autoload.php');

require_once "mydb.php";


try {
		$client_id = '785966582104-p03j542fcviuklf0kka21ushko2i7k0a.apps.googleusercontent.com';
		$client_secret = 'YnJO9pKNPeoD_cmdQDEyg-9X';
		$redirect_uri = 'https://googmesh.com/gilamos/drive.php';
		syslog(LOG_INFO, "getFolder called, orderID: ".$orderID); 
		$client = new Google_Client();
		$client->setClientId($client_id);
		$client->setClientSecret($client_secret);
		$client->setRedirectUri($redirect_uri);
	
		$client->addScope("https://www.googleapis.com/auth/drive.file");
		//$client->setScopes("https://www.googleapis.com/auth/drive");
	
		$file = new Google_Service_Drive_DriveFile();
		$file->setTitle($orderID);
		//$file->setDescription($description);
		$file->setMimeType("application/vnd.google-apps.folder");	// folder
	
		/************************************************
		  Boilerplate auth management - see
		  user-example.php for details.
		 ************************************************/
		if (isset($_REQUEST['logout'])) {
		  	unset($_SESSION['access_token']);
		}
		if (isset($_GET['code'])) {
		  	$client->authenticate($_GET['code']);
		  	$_SESSION['access_token'] = $client->getAccessToken();
		  	$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		  	header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
		}
	
		if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
		  	$client->setAccessToken($_SESSION['access_token']);

		} else {
			syslog(LOG_INFO, "creating auth url");
		  	$authUrl = $client->createAuthUrl();
		}


		if($client->isAccessTokenExpired()) {
			 //$client->refreshToken($client->getAccessToken());
			syslog(LOG_INFO, "creating auth url again");	 
			$authUrl = $client->createAuthUrl();
			header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
			
		}
		if ($client->getAccessToken()) {
			syslog(LOG_INFO, "got access token");	
		  	$_SESSION['access_token'] = $client->getAccessToken();

			$service = new Google_Service_Drive($client);

			$createdFile = $service->files->insert($file, array(
		     	// 'data' => $data,
		      	'mimeType' => $file->getMimeType(),
		    ));
			if ($createdFile)
				syslog(LOG_INFO, "Folder created: ".$orderID);
			else
				syslog(LOG_ERR, "Failed to create folder: ".$orderID);		
		}
		else {
			syslog(LOG_ERR, "Failed to get access token");	
		}

	}
	catch(Exception $e) {
		syslog (LOG_ERR, "Exception: " .$e->getMessage());
		echo "Exception: " .$e->getMessage();
		return null;	
	}





?>
