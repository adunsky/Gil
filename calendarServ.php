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

require_once realpath(dirname(__FILE__) . '/google-api-php-client-master/autoload.php');

require_once "mydb.php";
require_once "gmail.php";

//session_start();
   
	// get arguments from command line		
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	
	$dbName = $_GET['db'];
	//error_log( $dbName, 3, $logFileName);
			
	if (!selectDB($dbName))
		return;	
	getClientInfo($dbName); 	// to set the global $lang

	$clientList = getClientList($dbName);
	$logFileName = "cal".$custName.".log";

	$clients = [];
	$services = [];
	$creds = [];
	foreach ($clientList as $clientInfo) {
		// maintain a list of clients per customer for calendars scalability
		$clientNum = $clientInfo["clientNumber"];
		$clientid = $clientInfo["clientID"];
		$clientmail = $clientInfo["clientMail"];
		$clientkeypath = $clientInfo["clientKeyPath"];
		error_log( "Client: ".$clientNum." clientID: ".$clientid."\n", 3, $logFileName);

		$clients[$clientNum] = new Google_Client();
		$clients[$clientNum]->setApplicationName($appname);
		$clients[$clientNum]->setClientId($clientid);
		$services[$clientNum] = new Google_Service_Calendar($clients[$clientNum]);
		if (!$services[$clientNum])
			error_log( "Failed to create service: ".$clientNum."\n", 3, $logFileName);
		
		/************************************************
		  If we have an access token, we can carry on.
		  Otherwise, we'll get one with the help of an
		  assertion credential. In other examples the list
		  of scopes was managed by the Client, but here
		  we have to list them manually. We also supply
		  the service account
		 ************************************************/

		$key = file_get_contents($clientkeypath);
		$creds[$clientNum] = new Google_Auth_AssertionCredentials(
		    $clientmail,
		    array('https://www.googleapis.com/auth/calendar'),
		    $key
		);
		$clients[$clientNum]->setAssertionCredentials($creds[$clientNum]);
	}

	while (true) {
		date_default_timezone_set("Asia/Jerusalem");
		$currTime = date("M d H:i:s ");
		set_time_limit (0); // run forever
		try {	  
			foreach ($clients as $client) {
				// refresh clients if needed
				if ($client->getAuth()->isAccessTokenExpired()) {
				  error_log( $currTime."Refreshing client token: ".array_search($client, $clients)."\n", 3, $logFileName);	
				  $client->getAuth()->refreshTokenWithAssertion($creds[array_search($client, $clients)]);
				}
			}
			// Look for Emails to send
			checkForEmails();

			// Look for calendar events that needs update
			$sql = "SELECT * FROM $eventsTable WHERE updated='0';";
			if (!$result = mysql_query($sql)) {
				error_log( $currTime.'Select event table Failed! ' . mysql_error(), 3, $logFileName); 
				sleep(1);
				continue;
			}
			if ((mysql_num_rows($result) > 0) && ($event = mysql_fetch_array($result, MYSQL_ASSOC))) {
				// found event to update
				$orderID = $event["orderID"];
	    		$eventID = $event["eventID"];
	    		$calendarNum = $event["calendarID"];
	    		$date = $event["eventDate"];

	         	error_log( $currTime."found event to update in event table for order ". $orderID." Date: ".$date."\n", 3, $logFileName);
	         
				$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarNum';";
				if (!$result = mysql_query($sql)) {
					error_log( $currTime.'Select calendar table Failed! ' . mysql_error(), 3, $logFileName); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($calendar = mysql_fetch_array($result, MYSQL_ASSOC))) {
					// Found the calendar details and the google calendar ID
					$fieldIndex = $calendar["fieldIndex"];
					$calendarID = $calendar["calID"];
					$titleField = $calendar["titleField"];
					$locationField = $calendar["locationField"];
					if (array_key_exists("participants", $calendar))
						$participantsField = $calendar["participants"];
					else
						$participantsField = "";
					if (array_key_exists("count", $calendar))
						$calendarCount = $calendar["count"];
					else
						$calendarCount = 1;

				}
				$sql = "SELECT * FROM $mainTable WHERE id='$orderID';";
				if (!$result = mysql_query($sql)) {
					error_log( $currTime.'Select main table Failed! ' . mysql_error(), 3, $logFileName); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($order = mysql_fetch_array($result, MYSQL_ASSOC))) {
					// Found the order 
					$eventName = $order[$titleField];
					$location = $order[$locationField];
					if ($participantsField > 0)
						$participantList = explode(",", $order[$participantsField]);	
					else
						$participantList = [];
								
				}
	
				$date1 = "";
				$date2 = "";
				// query the fields table to find if it is a start time
				$sql = "SELECT * FROM $fieldTable WHERE `index`='$fieldIndex';";
				if (!$result = mysql_query($sql)) {
					error_log( $currTime.'Select field table Failed! ' . mysql_error(), 3, $logFileName); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "STARTTIME") === 0) {
						$twinNum = substr($type1, strlen("STARTTIME")); // this is the index of the start-end twin
						$type2 = "ENDTIME".$twinNum;
						error_log( $currTime."found end time: ".$type2."\n", 3, $logFileName);							
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						if (!$result = mysql_query($sql)) {
							error_log( $currTime.'Select field table Failed! ' . mysql_error(), 3, $logFileName); 
							continue;
						}
						if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
							$fieldIndex2 = $field["index"];							
							$date2 = $order[$fieldIndex2];	// found the endtime
							$date1 = $date;						// this is the start time
						}			
					}

				}

				// query the fields table to find if it is an end time
				$sql = "SELECT * FROM $fieldTable WHERE `index`='$fieldIndex';";
				if (!$result = mysql_query($sql)) {
					error_log( $currTime.'Select field table Failed! ' . mysql_error(), 3, $logFileName); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "ENDTIME") === 0) {
						$twinNum = substr($type1, strlen("ENDTIME")); // this is the index of the start-end twin
						$type2 = "STARTTIME".$twinNum;
						// error_log( $currTime."looking for: ".$type2."\n", 3, $logFileName);
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						if (!$result = mysql_query($sql)) {
							error_log( $currTime.'Select field table Failed! ' . mysql_error(), 3, $logFileName); 
							continue;
						}
						if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
							$fieldIndex2 = $field["index"];							
							$date1 = $order[$fieldIndex2];	// found the start time
							$date2 = $date;	// this is the end time
							// error_log( $currTime."Found starttime: ".$date1."\n", 3, $logFileName);
						}			
					}

				}				
			
	  		}
			else { 
			 	//error_log( $currTime."No event to update", 3, $logFileName);
				flush();
			  	sleep(1);
			  	continue;
			}	
			
			$calEvent = null;
			//error_log( $currTime."eventID= ".$eventID."\n", 3, $logFileName);

			$googleClient = getClientForCalendar($calendarCount);

			if ($eventID && $eventID != ""){
				// look for the event in the calendar

				$params = [];
				$params["maxResults"] = 2500;	// max number of events per calendar
				$params["q"] =  '"Order ID :'.$orderID.'"';		// search by orderID in the dscription
		    	$list = $services[$googleClient]->events->listEvents($calendarID, $params);	
				foreach($list["items"] as $eventx) {
					//error_log( $currTime."eventx ID= ".$eventx["htmlLink"]."\n", 3, $logFileName);
					if ($eventID == $eventx["id"] || strpos($eventx["htmlLink"], $eventID ) != false) {
		    			error_log( $currTime." found existing event in calendar: ". $calendarID."\n", 3, $logFileName);
		    			$calEvent = $eventx;
		    			break;
					}
					else {	// event ID does not exist in our DB - remove it
						// $services[$googleClient]->events->delete($calendarID, $eventx->getId());
					}	
				
				}
			}
			$new = false;
			if (!$calEvent) {
				error_log( $currTime."Event doesn't exist for order ".$orderID."\n", 3, $logFileName);
				$calEvent = new Google_Service_Calendar_Event();
				$new = true;
			}

			if (!strtotime($date) || ($date == '0000-00-00 00:00:00')) {
				error_log( $currTime."Removing event with invalid date: ".$date."\n", 3, $logFileName);
				// The new event date is empty or not valid - remove the event from the calendar
				if (!$new && $calEvent)
					$services[$googleClient]->events->delete($calendarID, $calEvent->getId());
				// Remove the event from the events table
				$sql = "DELETE FROM $eventsTable WHERE calendarID='$calendarNum' AND orderID='$orderID' AND eventID = '$eventID';";
				if (!$result = mysql_query($sql)) {
					error_log( $currTime.'Delete from events table Failed! ' . mysql_error(), 3, $logFileName); 
				}
				continue; // Don't continue to process this event
			}
			
			$eventChanged = false;
			if ($calEvent->getSummary() != $eventName) {
				$calEvent->setSummary($eventName);
				error_log( $currTime."New title: ".$eventName."\n", 3, $logFileName);
				$eventChanged = true;
			}
			if ($calEvent->getLocation() != $location) {
				$calEvent->setLocation($location);
				$eventChanged = true;
			}

			$allDay = false;
			// if there are no 2 valid dates it is an all day event
			if ($date1 == "" || $date2 == "" || $date1 == $date2 || !strtotime($date1) || !strtotime($date2) || strtotime($date1) >= strtotime($date2) ) {
				$allDay = true;
				$date1 = $date2 = $date;	// It is an all day event
			}
			$start = new Google_Service_Calendar_EventDateTime();
			$timestamp1 = strtotime($date1);
			if ($allDay) {
				// format it without the time
				$date = date("Y-m-d", $timestamp1);
				$start->setDate($date);				
			}	// format with time
			else {
				$start->setTimeZone("Asia/Jerusalem");
				$date = date('Y-m-d\TH:i:s', $timestamp1);
				$start->setDateTime($date);
				error_log( $currTime."Start: ".$date."\n", 3, $logFileName);
			}
			$wasStart = $calEvent->getStart();
			//error_log( $currTime."Was start: ", 3, $logFileName);
			//var_dump($wasStart);
			if ($wasStart != $start) {
				$calEvent->setStart($start);
				//error_log( $currTime." New start: ", 3, $logFileName);
				//var_dump($start);
				$eventChanged = true;
			}					

			$end = new Google_Service_Calendar_EventDateTime();
			if ($allDay) {
				$timestamp2 = strtotime($date."+1 days"); // end date is one day later
				$date2 = date("Y-m-d", $timestamp2);					
				//error_log( $currTime."End: ".$date2."\n", 3, $logFileName);
				$end->setDate($date2);	// no time set - all day event
			}		
			else { 
				$timestamp2 = strtotime($date2);	
				// set the end time
				$end->setTimeZone("Asia/Jerusalem");
				$endDate = date('Y-m-d\TH:i:s', $timestamp2); // Back to string
				$end->setDateTime($endDate);
				error_log( $currTime."End: ".$endDate."\n", 3, $logFileName);
			}
			if ($calEvent->getEnd() != $end) {
				$calEvent->setEnd($end);
				//var_dump($end);
				$eventChanged = true;
			}					

			/*
			$gadget = new Google_Service_Calendar_EventGadget();
			$gadget->setDisplay("icon");
			$gadget->setIconLink("https://www.thefreedictionary.com/favicon.ico");
			$gadget->setLink("https://googlemesh.com/Gilamos/hello.xml");
			$gadget->setType("application/x-google-gadgets+xml");
		  	$gadget->setHeight(236);
		   	$gadget->setWidth(600);
			//$gadget->setType("html");
			$gadget->setTitle("Gil's Gadget");
			
			$calEvent->gadget = $gadget;
			*/
			$attendees = [];
			foreach($participantList as $participant) {
				// Remove all illegal characters from email
				$email = filter_var($participant, FILTER_SANITIZE_EMAIL);
				if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				  	// it is a valid email address");
					$attendee = new Google_Service_Calendar_EventAttendee();
					$attendee->setEmail($email);
					array_push($attendees, $attendee);
					error_log( $currTime."Participant added: ".$email."\n", 3, $logFileName);
				}
				else
					error_log( $currTime."Invalid participant email ignored: ".$participant."\n", 3, $logFileName);
			}
			if (count($attendees) > 0) {
				//if (count(array_diff($attendees, $calEvent->getAttendees()))>0) {
					// update the event attendees
					$calEvent->attendees = $attendees;
					$eventChanged = true;
				}
			// set text direction for the description according to the language of the first word...
			$linkTitle = $lang == 'eng' ? 'Update' : 'עדכון';
			$description = "<a href=http://googlemesh.com/Gilamos/#/newOrder?db=".$dbName."&orderID=".$orderID."&calendarNum=".$calendarNum.">".$linkTitle."</a>
							<p>Order ID :".$orderID."<br>".getSearchFields($order)."</p>";
			if($new) {
				// error_log( $currTime."Calendar: ".$calendarNum." Client: ".$googleClient."\n", 3, $logFileName);
				// insert a new event with description with the orderID, so we can find it				
				$calEvent->setDescription($description);
				$updatedEvent = $services[$googleClient]->events->insert($calendarID, $calEvent);
				$eidpos = strpos($updatedEvent["htmlLink"], "eid=" ); // find the event ID that will show up in the map gadget
				$eventID = substr($updatedEvent["htmlLink"], $eidpos+4);
				$calEvent = $updatedEvent;
				$eventChanged = false;
				error_log( $currTime."New event created for orderID: ".$orderID."\n", 3, $logFileName);
			}
			else  // existing event - update the description if needed
				if ($calEvent->getDescription() != $description) {
					//error_log( $currTime."old description: ".$calEvent->getDescription()."", 3, $logFileName);
					//error_log( $currTime."new description: ".$description."", 3, $logFileName);
					$calEvent->setDescription($description);
					$eventChanged = true;
				}
			if ($eventChanged) {
				$updatedEvent = $services[$googleClient]->events->update($calendarID, $calEvent->getId(), $calEvent);
				//var_dump($updatedEvent);
				error_log( $currTime."Existing event updated\n", 3, $logFileName);
			}
			$sql = "UPDATE $eventsTable set eventID='$eventID', updated='1' WHERE calendarID='$calendarNum' AND orderID='$orderID';";
			if (!$result = mysql_query($sql)) {
				error_log( $currTime.'Update events table Failed! ' . mysql_error(), 3, $logFileName); 
				continue;
			}
			else
				error_log( $currTime."Event table updated\n", 3, $logFileName);
	

		}	// try
		//catch exception
		catch(Exception $e) {
			  error_log( $currTime.'Exception: ' .$e->getMessage(). "", 3, $logFileName);
			  syslog(LOG_ERR, "Exception in Calendar service: ".$e->getMessage());
			  sleep(1);
			  continue;
		}	

		
	
	} // while (true)



function getSearchFields($order) {
	global $fieldTable;

	$sql = "SELECT * FROM $fieldTable WHERE searchable='Y';";
	if (!$result = mysql_query($sql)) {
		error_log( $currTime.'Select search fields Failed! ' . mysql_error(), 3, $logFileName); 
		return "";
	}
	$str = "";
	while ($field = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$searchFieldIndex = $field['index'];
		$value = $order[$searchFieldIndex];
		$str = $str."<b>".$field['name'].": </b>".$value." <br>";

	}
	return $str;

}

function checkForEmails() {
	global $emailsTable;

	$currTime = date("M d H:i:s ");
	// Look for awaiting emails in the emails table
	$sql = "SELECT * FROM $emailsTable WHERE updated='0';";
	if (!$result = mysql_query($sql)) {
		error_log( $currTime.'Select emails table Failed! ' . mysql_error(), 3, $logFileName); 
		return;
	}
	while ($email = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$num = $email["num"];
		$orderID = $email["orderID"];
		// found email to send
		if (strtotime($email["schedule"]) <= strtotime($currTime)) {
			sendMail($email["emailTo"], $email["fromName"], $email["fromEmail"], $email["subject"], $email["content"]);
			mysql_query("UPDATE $emailsTable SET updated='1' WHERE num='$num' AND orderID='$orderID';");
			error_log( $currTime."Sent email to: ".$email["emailTo"]." subject: ".$email["subject"]."\n", 3, $logFileName);
		}
	}
}


?>



