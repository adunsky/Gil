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
session_start();

require_once realpath(dirname(__FILE__) . '/google-api-php-client-master/autoload.php');

require_once "mydb.php";
require_once "spreadsheet.php";

 
$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
$clientkeypath = 'API Project-0ffd21d566b5.p12';

$client = new Google_Client();
$client->setApplicationName($appname);
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
    array('https://www.googleapis.com/auth/calendar',
    		 'https://spreadsheets.google.com/feeds'),
    $key
);
$client->setAssertionCredentials($cred);

while (true) {
	
		set_time_limit (0); // run forever
			  
		
		if ($client->getAuth()->isAccessTokenExpired()) {
		  $client->getAuth()->refreshTokenWithAssertion($cred);
		}
			   
		if ($client->getAccessToken()) {
			  	$_SESSION['service_token'] = $client->getAccessToken();
			
		// select rows with update time > curr time			

			$sql = "SELECT * FROM $eventsTable WHERE updated='0';";
			$result = mysql_query($sql) or die('Select event Failed! ' . mysql_error()); 
			if ((mysql_num_rows($result) > 0) && ($event = mysql_fetch_array($result, MYSQL_ASSOC))) {
				$orderID = $event["orderID"];
	    		$eventID = $event["eventID"];
	    		$calendarNum = $event["calendarID"];
	    		$fieldIndex = $event["fieldIndex"];
	    		$date = $event["eventDate"];

	         echo "found event for order ". $orderID." Date: ".$date."<br>\n";
	         
				$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarNum';";
				$result = mysql_query($sql) or die('Select calendar Failed! ' . mysql_error()); 
				if ((mysql_num_rows($result) > 0) && ($calendar = mysql_fetch_array($result, MYSQL_ASSOC))) {
					// Found the calendar details and the google calendar ID
					$calendarID = $calendar["calID"];
					$titleField = $calendar["titleField"];
					$locationField = $calendar["locationField"];				
				
				}
				$sql = "SELECT * FROM $mainTable WHERE id='$orderID';";
				$result = mysql_query($sql) or die('Select order Failed! ' . mysql_error()); 
				if ((mysql_num_rows($result) > 0) && ($order = mysql_fetch_array($result, MYSQL_ASSOC))) {
					// Found the order 
					$eventName = $order[$titleField];
					$location = $order[$locationField];						
				}
				

				$date1 = "";
				$date2 = "";
				// query the fields table to find if it is a start time
				$sql = "SELECT * FROM $fieldTable WHERE `index`='$fieldIndex';";
				$result = mysql_query($sql) or die('Select field Failed! ' . mysql_error()); 
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "STARTTIME") === 0) {
						$twinNum = substr($type1, strlen("STARTTIME")); // this is the index on the start-end twin
						$type2 = "ENDTIME".$twinNum;
						
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						$result = mysql_query($sql) or die('Select field Failed! ' . mysql_error()); 
						if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
							$fieldIndex2 = $field["index"];							
							$date2 = $order[$fieldIndex2];	// found the endtime
							$date1 = $date;						// this is the start time
						}			
					}

				}

				// query the fields table to find if it is an end time
				$sql = "SELECT * FROM $fieldTable WHERE `index`='$fieldIndex';";
				$result = mysql_query($sql) or die('Select field Failed! ' . mysql_error()); 
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "ENDTIME") === 0) {
						$twinNum = substr($type1, strlen("ENDTIME")); // this is the index on the start-end twin
						$type2 = "STARTTIME".$twinNum;
						// echo "looking for: ".$type2."<br>\n";
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						$result = mysql_query($sql) or die('Select field Failed! ' . mysql_error()); 
						if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
							$fieldIndex2 = $field["index"];							
							$date1 = $order[$fieldIndex2];	// found the start time
							$date2 = $date;	// this is the end time
							// echo "Found starttime: ".$date1."<br>\n";
						}			
					}

				}				
			

	  		}
			else { 
			 	//echo "No event to update<br>";
				flush();
			  	sleep(1);
			  	continue;
			}	
			
			$calEvent = null;
			//echo "eventID= ".$eventID."<br>\n";
			if ($eventID && $eventID != ""){
				// look for the event in the calendar
				$params = [];
				$params["maxResults"] = 2500;	// max number of events per calendar
		    	$list = $service->events->listEvents($calendarID, $params);	
				foreach($list["items"] as $eventx) {
					// echo "eventx ID= ".$eventx["htmlLink"]."<br>\n";
					if ($eventID == $eventx["id"] || strpos($eventx["htmlLink"], $eventID ) != false) {
		    			echo " found event in calendar: ". $calendarID."<br>\n";
		    			$calEvent = $eventx;
		    			break;
					}	
				
				}
			}
			$new = false;
			if (!$calEvent) {
				echo "Event doesn't exist - creating new...<br>\n";
				$calEvent = new Google_Service_Calendar_Event();
				$new = true;
			}

			if (!strtotime($date) || ($date == '0000-00-00 00:00:00')) {
				echo "Removing event with invalid date: ".$date."<br>\n";
				// The new event date is empty or not valid - remove the event from the calendar
				if (!$new && $calEvent)
					$service->events->delete($calendarID, $calEvent->getId());
				// Remove the event from the events table
				$sql = "DELETE FROM $eventsTable WHERE calendarID='$calendarNum' AND fieldIndex='$fieldIndex' AND orderID='$orderID' AND eventID = '$eventID';";
				$result = mysql_query($sql) or die('Delete event Failed! ' . mysql_error());
				continue; // Don't continue to process this event
			}		
			

			$calEvent->setSummary($eventName);
			$calEvent->setLocation($location);
			$allDay = false;
			if ($date1 == "" || $date2 == "") {
				$allDay = true;
				$date1 = $date2 = $date;	// It is an all day event
			}
			$start = new Google_Service_Calendar_EventDateTime();
			$start->setTimeZone("Asia/Jerusalem");
			$timestamp1 = strtotime($date1);
			if ($allDay) {
				// format it without the time
				$date = date("Y-m-d", $timestamp1);
				$start->setDate($date);				
			}	// format with time
			else {
				$date = date("Y-m-d\TH:i:s", $timestamp1);
				$start->setDateTime($date);
				echo "Start: ".$date."<br>\n";
			}
			$calEvent->setStart($start);

			$timestamp2 = strtotime($date2);			
			$end = new Google_Service_Calendar_EventDateTime();
			$end->setTimeZone("Asia/Jerusalem");
			if ($allDay) {
				$end->setDate($date);	// no time set - all day event
			}		
			else { 
				// set the end time
				$endDate = date('Y-m-d\TH:i:s', $timestamp2); // Back to string
				$end->setDateTime($endDate);
				echo "End: ".$endDate."<br>\n";
			}
			$calEvent->setEnd($end);
			//$calEvent->setDescription($description);
			// all day event ?
/*
			
			$gadget = new Google_Service_Calendar_EventGadget();
			$gadget->setDisplay("chip");
			$gadget->setIconLink("https://localhost/gil/done.jpg");
			$gadget->setLink("https://amosgery.5gbfree.com/gil/myEvents1.xml");
			//$gadget->setType("application/x-google-gadgets+xml");
		  	$gadget->setHeight(236);
		   $gadget->setWidth(600);
			$gadget->setType("html");
			$gadget->setTitle("Gil's Gadget");
			
			$calEvent->gadget = $gadget;
*/			
			$attendee1 = new Google_Service_Calendar_EventAttendee();
			$attendee1->setEmail('gildavidov7@gmail.com');
			// ...
			$attendees = array($attendee1,
			                   // ...
			                  );
			//$calEvent->attendees = $attendees;

			try {
				if($new) {
					$updatedEvent = $service->events->insert($calendarID, $calEvent);
					$eidpos = strpos($updatedEvent["htmlLink"], "eid=" ); // find the event ID that will show up in the map gadget
					$eventID = substr($updatedEvent["htmlLink"], $eidpos+4);
					$calEvent = $updatedEvent;
				}
				
				$calEvent->setDescription("<p>Order ID :".$orderID."</p><br><a href='http://h06dolon1.securedcloudassets.com/Gilamos?id=".$eventID."#/newOrder'>Update</a>");
				$updatedEvent = $service->events->update($calendarID, $calEvent->getId(), $calEvent);
				$sql = "UPDATE $eventsTable set eventID='$eventID', updated='1' WHERE calendarID='$calendarNum' AND fieldIndex='$fieldIndex' AND orderID='$orderID';";
				$result = mysql_query($sql) or die('Update event Failed! ' . mysql_error());
		

			}
			//catch exception
			catch(Exception $e) {
				  echo 'Exception: ' .$e->getMessage(). "<br>";
				  sleep(1);
				  continue;
			}	

			
		} 
		else {
				echo "could not access calendar";
				return;
		}  

} // while (true)

?>



