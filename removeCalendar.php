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

 	$postdata = file_get_contents("php://input");
 	//echo "postdata: " . $postdata;

 	$calendar = json_decode($postdata, true);
	$dbName = $calendar["dbName"];

	$calendarName = $calendar["name"];
	$calendarDateField = $calendar["fieldIndex"];

	if (!selectDB($dbName))
		return;	
		
	$ssName = getClientInfo($dbName);
	if ($ssName)
		setSSName($ssName);
	else {
		echo "spreadsheet not found for DB: ".$dbName;
		return;		
	}		
				
	set_time_limit (0); // This may take a while
	$sql = "SELECT * FROM $calendarsTable WHERE name='$calendarName';";
	$result = mysql_query($sql) or die("SELECT calendar from DB Failed! " . mysql_error());

	if (($calCount = mysql_num_rows($result)) <= 0) {
		echo "Calendar: ".$calendarName." doesn't exist";
		return;
	}
	$calID = $calendar["calID"];
	$count = $calendar["count"];
	$calNum = $calendar["number"];	
	if ($calCount > 1) {
		echo "Removing calendar: ".$calNum;
		// Remove it from the calendars table
		$sql = "DELETE FROM $calendarsTable WHERE number='$calNum';";
		if (!$result = mysql_query($sql)) {
			echo "DELETE calendar from DB Failed! " . mysql_error();
			return;
		}
		// remove only the events for this date key
		$sql = "UPDATE $eventsTable SET eventDate='0000-00-00 00:00:00', updated=0 WHERE calendarID='$calNum';";
		if (!$result = mysql_query($sql)) {
			echo "DELETE events from DB Failed! " . mysql_error();
			return;
		}

	}
	else {
		// remove the calendar from Google
		if (!deleteCalendar($calID, $count)) 
			echo "Deletion of Google calendar ID: ".$calID. " Failed!<br>\n";
			// ignore the error since the calendar was deleted anyway

		// Remove it from the calendars table
		$sql = "DELETE FROM $calendarsTable WHERE calID='$calID';";
		if (!$result = mysql_query($sql)) {
			echo "DELETE calendar from DB Failed! " . mysql_error();
			return;
		}
		// Remove the events for this calendar from the events table
		$sql = "DELETE FROM $eventsTable WHERE calendarID='$calNum';";
		if (!$result = mysql_query($sql)) {
			echo "DELETE events from DB Failed! " . mysql_error();
			return;
		}
	}


?>
