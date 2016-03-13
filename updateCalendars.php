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

 	$data = json_decode($postdata, true);
	$dbName = $data["dbName"];
	$calendarList = $data["calendarList"];


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

	foreach ($calendarList as $calendar) {
		//var_dump($calendar);
		$calendarName = $calendar["name"];
		$fieldIndex = $calendar["fieldIndex"];
		$calID = $calendar["calID"];
		$count = $calendar["count"];
		$calNum = $calendar["number"];
		$titleField = $calendar["titleField"];
		$locationField = $calendar["locationField"];
		$participants = $calendar["participants"];
		$formNumber = $calendar["formNumber"];

		if ($calID == "") {
			// new calendar
			$res = mysql_query("SELECT MAX(number) AS maxNum FROM $calendarsTable") or die('select calendar Failed! ' . mysql_error()); 
			$num = mysql_fetch_array($res);
			$maxNum = $num["maxNum"];
			$calNum = $maxNum+1;

			$sql = "SELECT * FROM $calendarsTable WHERE name='$calendarName';";
			$result = mysql_query($sql) or die('select calendar Failed! ' . mysql_error()); 
			if (mysql_num_rows($result) > 0) {
				// existing calendar
				$existingCal = mysql_fetch_array($result);
				$calID = $existingCal["calID"];
				$count = $existingCal["count"];
			}
			else {
				// new google calendar - create it
				$res = mysql_query("SELECT MAX(count) AS maxCount FROM $calendarsTable") or die('select calendar Failed! ' . mysql_error()); 
				$num = mysql_fetch_array($res);
				$count = $num["maxCount"] + 1;
				if (!$calID = createCalendar($calendarName, $count))
					echo "Create calendar: ".$calendarName." failed";
			}
			$sql = "INSERT INTO $calendarsTable VALUES('$calNum', '$count', '$calendarName', '$fieldIndex', '$formNumber', '$titleField', '$locationField', '$participants', '$calID')";
			$result = mysql_query($sql) or die('insert calendar Failed! ' . mysql_error()); 
		}
		else { 
			// update existing calendar
			if (array_key_exists("nameChanged", $calendar))
				$nameChanged = $calendar["nameChanged"];
			else
				$nameChanged = false;
			if (array_key_exists("dateChanged", $calendar))
				$dateChanged = $calendar["dateChanged"];
			else
				$dateChanged = false;
			if (array_key_exists("titleChanged", $calendar))
				$titleChanged = $calendar["titleChanged"];
			else
				$titleChanged = false;
			if (array_key_exists("locationChanged", $calendar))
				$locationChanged = $calendar["locationChanged"];
			else
				$locationChanged = false;
			if (array_key_exists("participantsChanged", $calendar))
				$participantsChanged = $calendar["participantsChanged"];
			else
				$participantsChanged = false;
			if (array_key_exists("formChanged", $calendar))
				$formChanged = $calendar["formChanged"];
			else
				$formChanged = false;

			if ($nameChanged) {
				// rename this calendar
				if (!renameCalendar($calID, $count, $calendarName))
					echo "rename failed";
			}

			if ($nameChanged ||
				$dateChanged ||
				$titleChanged ||
				$locationChanged ||
				$participantsChanged ||
				$formChanged) {
				// update the calendars table 
				$sql = "UPDATE $calendarsTable SET name='$calendarName', fieldIndex='$fieldIndex', titleField='$titleField', locationField='$locationField', participants='$participants', formNumber='$formNumber' WHERE number='$calNum';";
				$result = mysql_query($sql) or die('update calendars Failed! ' . mysql_error()); 		

			}
		}
	}


?>
