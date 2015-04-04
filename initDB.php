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
 
include_once "google-api-php-client-master/examples/templates/base.php";
//session_start();

require_once realpath(dirname(__FILE__) . '/google-api-php-client-master/autoload.php');

require_once "mydb.php";
require_once "spreadsheet.php";
require_once "initCalendar.php";

require realpath(dirname(__FILE__) . '/php-google-spreadsheet-client-master/vendor/autoload.php');

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

 		set_time_limit (0); // This may take a while
		$spreadsheet = initGoogleAPI(); // from spreadsheet.php
		$worksheetFeed = $spreadsheet->getWorksheets();

		// Create FieldType table
		createFieldTypeTable($worksheetFeed, $fieldTable);
		
		// Create form and field form tables	
		createFormTables($worksheetFeed, $formsTable, $formFieldsTable);	

		// Create main table
		createMainTable($worksheetFeed, $fieldTable, $mainTable);

		// remove the google calendars
		removeCalendars($worksheetFeed, $calendarsTable);
		
				
		// Create calendar and tables
		createCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
		
		// Create events table		
		createEventsTable($worksheetFeed, $eventsTable); 
		
		// Create Users table		
		createUsersTable($worksheetFeed, $usersTable, $calendarsTable);
		

function createMainTable($worksheetFeed, $fieldTable, $mainTable)	{

	$sql = "DROP TABLE IF EXISTS $mainTable;";
	$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error()); 
		
	$mainSql = "CREATE TABLE $mainTable ( id INT(32) AUTO_INCREMENT PRIMARY KEY,";	
	$sql = "SELECT * FROM $fieldTable;";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found fields
		$col = 0;
		while ($column = mysql_fetch_array($result)) {
			if ($col > 0) $mainSql .= ",";
			
			$ID = $column["index"];
			$type = $column["type"];
			$mainSql .= "`$ID` $type";
			$col++;
		}

		$mainSql .= ");";
		
		// echo $sql;
		$result = mysql_query($mainSql) or die('Create table Failed! ' . mysql_error());
		echo "Table ".$mainTable." created with ".($col)." columns<br>"; 		
	}
	else {
		echo " Columns not found in table" ;
	}


}

function createFieldTypeTable($worksheetFeed, $fieldTable) {
		// create fieldType tabls
		$sql = "DROP TABLE IF EXISTS $fieldTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
				
		$sql = "CREATE TABLE $fieldTable ( `name` VARCHAR(32), `index` INT(32), `type` VARCHAR(32), `input` VARCHAR(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create FieldType table Failed! ' . mysql_error());
	
		echo "Table ".$fieldTable." created<br>"; 
		
		
		$worksheet = $worksheetFeed->getByTitle('CellType');
		$cellFeed = $worksheet->getCellFeed();

		$row = 2;
		$cellEntry = $cellFeed->getCell($row, 1);		
		while ($cellEntry && ($name = $cellEntry->getContent()) != "") {
			$cellEntry = $cellFeed->getCell($row, 2);	
			$type = $cellEntry->getContent();
			if ($type == 'TEXT')		// translate TEXT in the worksheet to VARCHAR(32)
				$type = 'VARCHAR(32)'; 
			$cellEntry = $cellFeed->getCell($row, 3);	
			$input = $cellEntry->getContent();
			
			$sql = "INSERT INTO $fieldTable VALUES ('$name', '$row', '$type', '$input');";
					// echo $sql;
			$result = mysql_query($sql) or die('Insert to fields table Failed! ' . mysql_error());
			$cellEntry = $cellFeed->getCell(++$row, 1);
		}
}

function createFormTables($worksheetFeed, $formsTable, $formFieldsTable) {
		// Create Form table	
		$sql = "DROP TABLE IF EXISTS $formsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		
		$sql = "CREATE TABLE $formsTable ( title VARCHAR(32), number INT(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Forms table Failed! ' . mysql_error());
	
		echo "Table ".$formsTable." created<br>"; 

		$sql = "DROP TABLE IF EXISTS $formFieldsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $formFieldsTable ( formNumber INT(32), fieldIndex INT(32), fieldType VARCHAR(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create fields table Failed! ' . mysql_error());
		
		echo "Table ".$formFieldsTable." created<br>"; 
		
		$worksheet = $worksheetFeed->getByTitle('Forms');
		$cellFeed = $worksheet->getCellFeed();
		
		$col = 2;
		$row = 1;
		
		$cellEntry = $cellFeed->getCell(1,$col);		
		while ($cellEntry && ($title = $cellEntry->getContent()) != "") {
			//echo $keys[$i];

			// insert forms to forms table
			$formNumber = $col-1;
			$sql = "INSERT INTO $formsTable VALUES ('$title', '$formNumber');";
					// echo $sql;
			$result = mysql_query($sql) or die('Insert to forms table Failed! ' . mysql_error());
			echo "Table ".$title." inserted to forms table<br>"; 
			
			// now insert the fields of the form
			$row = 2;
			$fieldTitle = $cellFeed->getCell($row, 1);
			while ($fieldTitle && $fieldTitle->getContent() != "") {
				$fieldCellEntry = $cellFeed->getCell($row, $col);
				if ($fieldCellEntry)	
					$type = $fieldCellEntry->getContent();	// The correct title with spaces	
				else 	
					$type = "";
					
				if ($type != "") {
					// add field to form
					$sql = "INSERT INTO $formFieldsTable VALUES ('$formNumber', '$row', '$type');";
					$result = mysql_query($sql) or die('Insert field Failed! ' . mysql_error());
					
				} 
				$fieldTitle = $cellFeed->getCell(++$row, 1);
			}
			$cellEntry = $cellFeed->getCell(1,++$col);				
		}
}


function removeCalendars($worksheetFeed, $calendarsTable) {
		// First remove the existing Google calendars
		$sql = "SHOW TABLES LIKE '$calendarsTable';";		
		$result = mysql_query($sql) or die('Show calendars table Failed! ' . mysql_error());
		if (mysql_num_rows($result) > 0)	{
			// calendars table exists
			$calendars = [];
			$sql = "SELECT * FROM $calendarsTable;";			
			$result = mysql_query($sql) or die('Get calendars Failed! ' . mysql_error());	
			while ($cal = mysql_fetch_array($result)) {
				$calID = $cal["calID"];
				if (!array_search($calID, $calendars))
					// add to calendars list if not already there
					array_push($calendars, $calID);	
			}		
			if (!deleteCalendars($calendars))
				die('Remove calendars Failed!');
		
		}


}


function createCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable) {
	
		// Drop the calendars table
		$sql = "DROP TABLE IF EXISTS $calendarsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		// Create Calendars table		
		$sql = "CREATE TABLE $calendarsTable ( number INT(32), name VARCHAR(32), fieldIndex INT(32), filter VARCHAR(32), formNumber INT(32), titleField INT(32) , locationField INT(32), calID VARCHAR(128));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Calendars table Failed! ' . mysql_error());
	
		echo "Table ".$calendarsTable." created<br>"; 
		
		$worksheet = $worksheetFeed->getByTitle('Calendars');
		$cellFeed = $worksheet->getCellFeed();
		
		$col = 2;
		
		// loop on columns to find all calendars
		$cellEntry = $cellFeed->getCell(1,$col);		
		while ($cellEntry && ($name = $cellEntry->getContent()) != "") {

			// insert calendar to calendars table
			$calNumber = $col-1;
			$calID = null;
			// loop on rows to find all date fields per calendar
			$row = 2;
			$fieldCell = $cellFeed->getCell($row, 1);
			while ($fieldCell && ($fieldCell->getContent()) != "") {
				$calCell = $cellFeed->getCell($row, $col);
				if ($calCell)	
					$filter = $calCell->getContent();	// The correct title with spaces	
				else 	
					$filter = "";
					
				if ($filter != "") {
					// create google calendar
					if (!$calID)
						$calID = createCalendar($worksheetFeed, $name);
					
					// add date to calendar table
					$sql = "INSERT INTO $calendarsTable VALUES ('$calNumber', '$name', '$row', '$filter', 0, 0, 0, '$calID');";
					$result = mysql_query($sql) or die('Insert field Failed! ' . mysql_error());
					
				} 
				$fieldCell = $cellFeed->getCell(++$row, 1);
			}
			$cellEntry = $cellFeed->getCell(1,++$col);				
		}
			
		// Read the calendar-form mapping worksheet
		$worksheet = $worksheetFeed->getByTitle('Calendar-Form');
		$cellFeed = $worksheet->getCellFeed();
		
		$row = 2;
		// loop on rows = calendars
		$cellEntry = $cellFeed->getCell($row, 1);		
		while ($cellEntry && ($calendarName = $cellEntry->getContent()) != "") {
			// go over columns to get the details per calendar
			$col = 2;
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$formName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$eventTitleFieldName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$locationFieldName = $fieldCell->getContent();
				
			// find the form ID
			$sql = "SELECT * FROM $formsTable WHERE title='$formName';";			
			$result = mysql_query($sql) or die('Get form Failed! ' . mysql_error());	
			if ($form = mysql_fetch_array($result))
				$formID = $form["number"];				

			// find the title field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$eventTitleFieldName';";			
			$result = mysql_query($sql) or die('Get title field Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$titleFieldIndex = $field["index"];				
				
			// find the location field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$locationFieldName';";			
			$result = mysql_query($sql) or die('Get location field Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$locationFieldIndex = $field["index"];				

			// update the calendar table with the calendar details
			$sql = "UPDATE $calendarsTable set formNumber=$formID, titleField=$titleFieldIndex, locationField=$locationFieldIndex WHERE name='$calendarName';";			
			mysql_query($sql) or die('Update calendar details Failed! ' . mysql_error());	
				
			$cellEntry = $cellFeed->getCell(++$row, 1);		
		}

}	

function createUsersTable($worksheetFeed, $usersTable, $calendarsTable) {

		$sql = "DROP TABLE IF EXISTS $usersTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $usersTable ( userName VARCHAR(32), email VARCHAR(32), calendarNum INT(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Users table Failed! ' . mysql_error());
		
		echo "Table ".$usersTable." created<br>"; 
	
		$worksheet = $worksheetFeed->getByTitle('Users');
		$cellFeed = $worksheet->getCellFeed();
		
		$row = 2;
		// loop on rows = calendars
		$cellEntry = $cellFeed->getCell($row, 1);		
		while ($cellEntry && ($userName = $cellEntry->getContent()) != "") {
			// go over columns to get the details per user
			$col = 2;
			$cellEntry = $cellFeed->getCell($row, $col);
			$email = $cellEntry->getContent();
			$cellEntry = $cellFeed->getCell($row, ++$col);
			while ($cellEntry && ($calendarName = $cellEntry->getContent()) != "") {
				// get the calendar number
				$sql = "SELECT * FROM $calendarsTable WHERE name='$calendarName';";			
				$result = mysql_query($sql) or die('Get calendar number Failed! ' . mysql_error());	
				if ($calendar = mysql_fetch_array($result)) {
					$calendarNum = $calendar["number"];
					$calID = $calendar["calID"];	
					
					if (shareCalendar($calID, $email)) {
						// insert to users table
						$sql = "INSERT INTO $usersTable VALUES ('$userName', '$email', '$calendarNum');";
						$result = mysql_query($sql) or die('Insert field Failed! ' . mysql_error());
					}
				}
				$cellEntry = $cellFeed->getCell($row, ++$col);
			}
			$cellEntry = $cellFeed->getCell(++$row, 1);
		}
}

function createEventsTable($worksheetFeed, $eventsTable) {

		$sql = "DROP TABLE IF EXISTS $eventsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $eventsTable ( calendarID INT(32), fieldIndex INT(32), orderID INT(32), eventDate DATE, eventID VARCHAR(128), updated TINYINT);";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Events table Failed! ' . mysql_error());
		
		echo "Table ".$eventsTable." created<br>"; 

}


?>