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


function initCalendar($clientNumber) {
	global $appName, $globalDBName;
	
	$customer = getClient($globalDBName, $clientNumber);

	$clientid = $customer["clientID"];
	$clientmail = $customer["clientMail"];
	$clientkeypath = $customer["clientKeyPath"];

	for ($i=0; $i<5; $i++) {	// retry to initialize 5 times before failing
		try {
			$client = new Google_Client();
			$client->setApplicationName($appName);
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
			
			if (isset($_SESSION[$clientid])) {
			  echo "Got token from session\n";	
			  $client->setAccessToken($_SESSION[$clientid]);
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
				  	$_SESSION[$clientid] = $client->getAccessToken();
				  	return $service;
			} 
			else {
					echo "could not access calendar";
			}  
		}
		catch(Exception $e) {
			echo "Exception: " .$e->getMessage() ."<br>\n";
		}			
	}
	return null;
}


function createCalendar($worksheetFeed, $name, $calNumber) {

 		$clientNumber = getClientForCalendar($calNumber);
		$service = initCalendar($clientNumber);
		$calendar = new Google_Service_Calendar_Calendar();
		$calendar->setSummary($name);
		$calendar->setTimeZone("Asia/Jerusalem");
		
		$createdCalendar = $service->calendars->insert($calendar);
		
		echo "Client: ".$clientNumber. " calendar id: ".$createdCalendar->getId()."<br>\n";
		
		return $createdCalendar->getId();		


} // createCalendar

function shareCalendar($calID, $email, $calNumber) {

 		$clientNumber = getClientForCalendar($calNumber);
		$service = initCalendar($clientNumber);
		$rule = new Google_Service_Calendar_AclRule();
		$scope = new Google_Service_Calendar_AclRuleScope();
		
		$scope->setType("user");
		$scope->setValue($email);
		$rule->setScope($scope);
		$rule->setRole("reader");
		//$rule->setRole("owner");
		
		$createdRule = $service->acl->insert($calID, $rule);
		echo "Rule: ".$createdRule->getId()."<br>\n";			
		
		return $createdRule;		

}


function deleteCalendar($calendar, $number) {
	 
	$service = initCalendar(getClientForCalendar($number));
	$success = true;	

	try {
		$service->calendars->delete($calendar);
		echo "calendar deleted: ".$calendar."<br>\n";
	}
	catch(Exception $e) {
	  echo 'Exception: ' .$e->getMessage(). "<br>\n";
	  $success = false;
	}

	return $success;
	
} // delete


?>



