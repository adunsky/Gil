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
//session_start();
   
	// get arguments from command line		
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	
	$dbName = $_GET['db'];
	//echo $dbName;
			
	if (!selectDB($dbName))
		return;	
	getClientInfo($dbName); 	// to set the global $lang

	$clientList = getClientList($dbName);

	$clients = [];
	$services = [];
	$creds = [];
	foreach ($clientList as $clientInfo) {
		// maintain a list of clients per customer for calendars scalability
		$clientNum = $clientInfo["clientNumber"];
		$clientid = $clientInfo["clientID"];
		$clientmail = $clientInfo["clientMail"];
		$clientkeypath = $clientInfo["clientKeyPath"];
		echo "Client: ".$clientNum." clientID: ".$clientid."\n";

		$clients[$clientNum] = new Google_Client();
		$clients[$clientNum]->setApplicationName($appname);
		$clients[$clientNum]->setClientId($clientid);
		$services[$clientNum] = new Google_Service_Calendar($clients[$clientNum]);
		if (!$services[$clientNum])
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
		$creds[$clientNum] = new Google_Auth_AssertionCredentials(
		    $clientmail,
		    array('https://www.googleapis.com/auth/calendar'),
		    $key
		);
		$clients[$clientNum]->setAssertionCredentials($creds[$clientNum]);
	}

	while (true) {
		
		set_time_limit (0); // run forever
		try {	  
			foreach ($clients as $client) {
				// refresh clients if needed
				if ($client->getAuth()->isAccessTokenExpired()) {
				  echo "Refreshing client token: ".array_search($client, $clients)."<br>\n";	
				  $client->getAuth()->refreshTokenWithAssertion($creds[array_search($client, $clients)]);
				}
			}
			// Select events that needs update
			$sql = "SELECT * FROM $eventsTable WHERE updated='0';";
			if (!$result = mysql_query($sql)) {
				echo 'Select event table Failed! ' . mysql_error(); 
				continue;
			}
			if ((mysql_num_rows($result) > 0) && ($event = mysql_fetch_array($result, MYSQL_ASSOC))) {
				// found event to update
				$orderID = $event["orderID"];
	    		$eventID = $event["eventID"];
	    		$calendarNum = $event["calendarID"];
	    		$date = $event["eventDate"];

	         	echo "found event for order ". $orderID." Date: ".$date."<br>\n";
	         
				$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarNum';";
				if (!$result = mysql_query($sql)) {
					echo 'Select calendar table Failed! ' . mysql_error(); 
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

					//$colorField = $calendar["colorField"];
					$colorField = 0;	// not used						
				}
				$sql = "SELECT * FROM $mainTable WHERE id='$orderID';";
				if (!$result = mysql_query($sql)) {
					echo 'Select main table Failed! ' . mysql_error(); 
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

					if ($colorField)
						$color = $order[$colorField];	
					else 
						$color = 0;										
				}
				

				$date1 = "";
				$date2 = "";
				// query the fields table to find if it is a start time
				$sql = "SELECT * FROM $fieldTable WHERE `index`='$fieldIndex';";
				if (!$result = mysql_query($sql)) {
					echo 'Select field table Failed! ' . mysql_error(); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "STARTTIME") === 0) {
						$twinNum = substr($type1, strlen("STARTTIME")); // this is the index of the start-end twin
						$type2 = "ENDTIME".$twinNum;
						echo "found end time: ".$type2."<br>\n";							
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						if (!$result = mysql_query($sql)) {
							echo 'Select field table Failed! ' . mysql_error(); 
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
					echo 'Select field table Failed! ' . mysql_error(); 
					continue;
				}
				if ((mysql_num_rows($result) > 0) && ($field = mysql_fetch_array($result, MYSQL_ASSOC))) {
					$type1 = $field["type"];
					if (strpos($type1, "ENDTIME") === 0) {
						$twinNum = substr($type1, strlen("ENDTIME")); // this is the index of the start-end twin
						$type2 = "STARTTIME".$twinNum;
						// echo "looking for: ".$type2."<br>\n";
						// query the field table for the end date
						$sql = "SELECT * FROM $fieldTable WHERE type='$type2';";
						if (!$result = mysql_query($sql)) {
							echo 'Select field table Failed! ' . mysql_error(); 
							continue;
						}
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

			$googleClient = getClientForCalendar($calendarCount);

			if ($eventID && $eventID != ""){
				// look for the event in the calendar

				$params = [];
				$params["maxResults"] = 2500;	// max number of events per calendar
				$params["q"] = "Order ID :".$orderID;	// search by orderID in the dscription
		    	$list = $services[$googleClient]->events->listEvents($calendarID, $params);	
				foreach($list["items"] as $eventx) {
					//echo "eventx ID= ".$eventx["htmlLink"]."<br>\n";
					if ($eventID == $eventx["id"] || strpos($eventx["htmlLink"], $eventID ) != false) {
		    			echo " found event in calendar: ". $calendarID."<br>\n";
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
				echo "Event doesn't exist - creating new...<br>\n";
				$calEvent = new Google_Service_Calendar_Event();
				$new = true;
			}

			if (!strtotime($date) || ($date == '0000-00-00 00:00:00')) {
				echo "Removing event with invalid date: ".$date."<br>\n";
				// The new event date is empty or not valid - remove the event from the calendar
				if (!$new && $calEvent)
					$services[$googleClient]->events->delete($calendarID, $calEvent->getId());
				// Remove the event from the events table
				$sql = "DELETE FROM $eventsTable WHERE calendarID='$calendarNum' AND orderID='$orderID' AND eventID = '$eventID';";
				if (!$result = mysql_query($sql)) {
					echo 'Delete from events table Failed! ' . mysql_error(); 
				}
				continue; // Don't continue to process this event
			}
			
			$eventChanged = false;
			if ($calEvent->getSummary() != $eventName) {
				$calEvent->setSummary($eventName);
				echo "New title: ".$eventName."<br>\n";
				$eventChanged = true;
			}
			if ($calEvent->getLocation() != $location) {
				$calEvent->setLocation($location);
				$eventChanged = true;
			}
			if ($color) {
				echo "Color: " . $color."<br\n";
				$calEvent->setColorId(getColor($color));				
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
				echo "Start: ".$date."<br>\n";
			}
			$wasStart = $calEvent->getStart();
			//echo "Was start: ";
			//var_dump($wasStart);
			if ($wasStart != $start) {
				$calEvent->setStart($start);
				//echo " New start: ";
				//var_dump($start);
				$eventChanged = true;
			}					

			$end = new Google_Service_Calendar_EventDateTime();
			if ($allDay) {
				$timestamp2 = strtotime($date."+1 days"); // end date is one day later
				$date2 = date("Y-m-d", $timestamp2);					
				//echo "End: ".$date2."\n";
				$end->setDate($date2);	// no time set - all day event
			}		
			else { 
				$timestamp2 = strtotime($date2);	
				// set the end time
				$end->setTimeZone("Asia/Jerusalem");
				$endDate = date('Y-m-d\TH:i:s', $timestamp2); // Back to string
				$end->setDateTime($endDate);
				echo "End: ".$endDate."<br>\n";
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
					echo "Participant added: ".$email."\n";
				}
				else
					echo "Invalid participant email ignored: ".$participant."\n";
			}
			if (count($attendees) > 0) {
				//if (count(array_diff($attendees, $calEvent->getAttendees()))>0) {
					// update the event attendees
					$calEvent->attendees = $attendees;
					$eventChanged = true;
				}
			if($new) {
				echo "Calendar: ".$calendarNum." Client: ".$googleClient."\n";
				// insert a temporary description with the orderID, so we can find it				
				$description = "<p>Order ID :".$orderID."<br></p>";
				$calEvent->setDescription($description);
				$updatedEvent = $services[$googleClient]->events->insert($calendarID, $calEvent);
				$eidpos = strpos($updatedEvent["htmlLink"], "eid=" ); // find the event ID that will show up in the map gadget
				$eventID = substr($updatedEvent["htmlLink"], $eidpos+4);
				$calEvent = $updatedEvent;
			}
			// set text direction for the description according to the language of the first word...
			$linkTitle = $lang == 'eng' ? 'Update' : 'עדכון';
			$description = "<a href=http://googlemesh.com/Gilamos/#/newOrder?id=".$eventID."&db=".$dbName.">".$linkTitle."</a>
							<p>Order ID :".$orderID."<br>".getSearchFields($order)."</p>";
			if ($calEvent->getDescription() != $description) {
				//echo "old description: ".$calEvent->getDescription()."<br>";
				//echo "new description: ".$description."<br>";
				$calEvent->setDescription($description);
				$eventChanged = true;
			}
			if ($eventChanged) {
				$updatedEvent = $services[$googleClient]->events->update($calendarID, $calEvent->getId(), $calEvent);
				//var_dump($updatedEvent);
				echo "Event updated<br>\n";
			}
			$sql = "UPDATE $eventsTable set eventID='$eventID', updated='1' WHERE calendarID='$calendarNum' AND orderID='$orderID';";
			if (!$result = mysql_query($sql)) {
				echo 'Update events table Failed! ' . mysql_error(); 
				continue;
			}
	

		}	// try
		//catch exception
		catch(Exception $e) {
			  echo 'Exception: ' .$e->getMessage(). "<br>";
			  syslog(LOG_ERR, "Exception in Calendar service: ".$e->getMessage());
			  sleep(1);
			  continue;
		}	

		
	
	} // while (true)



function getSearchFields($order) {
	global $fieldTable;

	$sql = "SELECT * FROM $fieldTable WHERE searchable='Y';";
	if (!$result = mysql_query($sql)) {
		echo 'Select search fields Failed! ' . mysql_error(); 
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


$Colors  =  array( 
  		"cyan" => "1",
		"teal" => "2",
		"purple" => "3",
		"Magenta" => "4",
		"yellow" => "5",
		"orange" => "6",
		"Turquoise" => "7",
		"silver" => "8",
		"blue" => "9",
		"green" => "10",
		"red" => "11"
 );

        //  GetColor  returns  an  associative  array  with  the  red,  green  and  blue 
        //  values  of  the  desired  color 
        
	  function  getColor($Colorname) 
	  { 
	    global  $Colors; 
	    return  $Colors[$Colorname]; 
	  }
?>



