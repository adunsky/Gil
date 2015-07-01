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
		
		$calID = $_GET['id'];
		//echo $command;
		$dbName = $_GET['db'];
		//echo $dbName;
		$count = $_GET['count'];
		//echo $dbName;

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

 		if (deleteCalendar($calID, $count)) {
 			// Empty the calendars table
 			$sql = "DELETE FROM $calendarsTable WHERE calID='$calID';";
 			if (!$result = mysql_query($sql))
 				echo "DELETE calendar from DB Failed! " . mysql_error();

 		}
 		else {
 			echo "Deletion of Google calendar ID: ".$calID. " Failed!<br>\n";
 		}



?>
