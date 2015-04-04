<?php
/*
 * Copyright 2013 Google Inc.
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
//session_start();

require_once "google-api-php-client-master/autoload.php";

require_once "mydb.php";
require_once "spreadsheet.php";


function initCalendar() {
	$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
	$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
	$clientkeypath = 'API Project-0ffd21d566b5.p12';
	
	$client = new Google_Client();
	$client->setApplicationName("gil");
	$client->setClientId($clientid);
	$service = new Google_Service_Calendar($client);
	
	/************************************************
	  If we have an access token, we can carry on.
	  Otherwise, we'll get one with the help of an
	  assertion credential. In other examples the list
	  of scopes was managed by the Client, but here
	  we have to list them manually. We also supply
	  the service account
	 ************************************************/
	if (isset($_SESSION['service_token'])) {
	  $client->setAccessToken($_SESSION['service_token']);
	}
	$key = file_get_contents($clientkeypath);
	$cred = new Google_Auth_AssertionCredentials(
	    $clientmail,
	    array('https://www.googleapis.com/auth/calendar'),
	    $key
	);
	$client->setAssertionCredentials($cred);
	
	
	if ($client->getAuth()->isAccessTokenExpired()) {
	  $client->getAuth()->refreshTokenWithAssertion($cred);
	}
		   
	if ($client->getAccessToken()) {
		  	$_SESSION['service_token'] = $client->getAccessToken();
		  	return $service;
	} 
	else {
			echo "could not access calendar";
			return null;
	}  

}


function createCalendar($worksheetFeed, $name) {
 
		$service = initCalendar();
		$calendar = new Google_Service_Calendar_Calendar();
		$calendar->setSummary($name);
		$calendar->setTimeZone("Asia/Jerusalem");
		
		$createdCalendar = $service->calendars->insert($calendar);
		
		echo "calendar id: ".$createdCalendar->getId()."<br>\n";
		
		return $createdCalendar->getId();		


} // createCalendar



function shareCalendar($calID, $email) {
 
		$service = initCalendar();
		$rule = new Google_Service_Calendar_AclRule();
		$scope = new Google_Service_Calendar_AclRuleScope();
		
		$scope->setType("user");
		$scope->setValue($email);
		$rule->setScope($scope);
		$rule->setRole("reader");
		
		$createdRule = $service->acl->insert($calID, $rule);
		echo "Rule: ".$createdRule->getId()."<br>\n";			
		
		return $createdRule;		

}


function deleteCalendars($calendars) {
	 
	$service = initCalendar();		
	foreach ($calendars as $calendar) {
		try {
			$service->calendars->delete($calendar);
			echo "calendar deleted: ".$calendar."<br>\n";
		}
		catch(Exception $e) {
		  echo 'Exception: ' .$e->getMessage(). "<br>\n";
		  //return false;
		}
	}
	return true;
} // delete


?>



