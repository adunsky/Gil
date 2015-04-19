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
	    		/*
   			$location = $event["location"];
   			$dateArray = explode(" ", $order["date1"], 2);
   			// Format the date and time to calendar format
   			$date1 = $dateArray[0]."T".$dateArray[1]."Z";
   			//echo "date: ".$date1;
   			$customerID = $order["customerID"];
   			*/
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

			if (!strtotime($date) || ($date == '0000-00-00')) {
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
		
			$start = new Google_Service_Calendar_EventDateTime();
			$start->setDate($date);
			$calEvent->setStart($start);
			$end = new Google_Service_Calendar_EventDateTime();
			$end->setDate($date);
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



