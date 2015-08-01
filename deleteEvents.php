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
 
include_once realpath(dirname(__FILE__) . "/google-api-php-client-master/examples/templates/base.php");
//session_start();

require_once realpath(dirname(__FILE__) . '/google-api-php-client-master/autoload.php');

require_once "mydb.php";
require_once "spreadsheet.php";
require_once "initCalendar.php";

require realpath(dirname(__FILE__) . '/php-google-spreadsheet-client-master/vendor/autoload.php');

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

		// get arguments from command line		
		parse_str(implode('&', array_slice($argv, 1)), $_GET);
		
		$calNum = $_GET['calendar'];
		//echo $command;
		$dbName = $_GET['db'];
		//echo $dbName;
		$orderID = $_GET['orderID'];

		if (!selectDB($dbName))
			return;	
			
		getClientInfo($dbName); 	// to set the global $lang

		 		      
 		$sql = "SELECT * FROM $calendarsTable WHERE number = '$calNum';";
 		if (!$result = mysql_query($sql)) {
 			echo 'Select calendar table Failed! ' . mysql_error(); 
 			return;
 		}
 		if ((mysql_num_rows($result) > 0) && ($calendar = mysql_fetch_array($result, MYSQL_ASSOC))) {
 			// Found the calendar details and the google calendar ID
 			$calendarID = $calendar["calID"];
 			$calendarCount = $calendar["count"];
		}

		$clientList = getClientList($dbName);

		// select the client for this calendar
		$clientInfo = $clientList[getClientForCalendar($calendarCount)-1];

		// maintain a list of clients per customer for calendars scalability
		$clientNum = $clientInfo["clientNumber"];
		$clientid = $clientInfo["clientID"];
		$clientmail = $clientInfo["clientMail"];
		$clientkeypath = $clientInfo["clientKeyPath"];
		echo "Client: ".$clientNum." clientID: ".$clientid."\n";

		$client = new Google_Client();
		$client->setApplicationName($appname);
		$client->setClientId($clientid);
		$service = new Google_Service_Calendar($client);
		if (!$service)
			echo "Failed to create service: ".$clientNum."\n";
		
		/************************************************
		  If we have an access token, we can carry on.
		  Otherwise, we'll get one with the help of an
		  assertion credential. In other examples the list
		  of scopes was managed by the Client, but here
		  we have to list them manually. We also supply
		  the service account
		 ************************************************/

		$key = file_get_contents($clientkeypath);
		$cred = new Google_Auth_AssertionCredentials(
		    $clientmail,
		    array('https://www.googleapis.com/auth/calendar'),
		    $key
		);
		$client->setAssertionCredentials($cred);

			
		// refresh clients if needed
		if ($client->getAuth()->isAccessTokenExpired()) {
		  echo "Refreshing client token: ".getClientForCalendar($calendarCount)."<br>\n";	
		  $client->getAuth()->refreshTokenWithAssertion($cred);
		}

 		set_time_limit (0); // This may take a while

		$complete = false;
		$params = [];

		if ($orderID)
			$params["q"] = $orderID;	// search by orderID in the dscription		

		$params["maxResults"] = 2500;	// max number of events per calendar
    	$list = $service->events->listEvents($calendarID, $params);	

    	while (true) {
    		try {
				foreach($list["items"] as $eventx) {
					$service->events->delete($calendarID, $eventx->getId());
					echo "Event deleted: ".$eventx->getId()."<br>\n";			
				}

				$pageToken = $list->getNextPageToken();
				if ($pageToken) {
				    $optParams = array('pageToken' => $pageToken);
				    $list = $service->events->listEvents($calendarID, $optParams);
				} else {
					$complete = true;
				  	break;
				}
			}
			catch(Exception $e) {
				  echo 'Exception: ' .$e->getMessage(). "<br>";
				  $list = $service->events->listEvents($calendarID, $params);	
				  sleep(1);
				  continue;
			}
		}

		if ($complete) {
			echo "Updating events table with updated=0 <br>\n";
			if ($orderID)
				$sql = "UPDATE $eventsTable SET updated=0 WHERE calendarID = '$calNum' AND orderID='$orderID';";	
			else	
				$sql = "UPDATE $eventsTable SET updated=0 WHERE calendarID = '$calNum';";
			if (!$result = mysql_query($sql)) {
				echo 'Update events table Failed! ' . mysql_error(); 
			}


		}


?>
