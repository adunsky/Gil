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
		
		$command = $_GET['cmd'];
		//echo $command;
		$dbName = $_GET['db'];
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
		$spreadsheet = initGoogleAPI(); // from spreadsheet.php
		$worksheetFeed = $spreadsheet->getWorksheets();
		if ($command == "clean") {
			// remove the google calendars and table
			removeCalendars($worksheetFeed, $calendarsTable, $eventsTable);
		}
		if ($command == "new") { 
			// recreate the DB - remove all the data !!!
			// Create FieldType table
			createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
		
			// Create form and field form tables	
			createFormTables($worksheetFeed, $formsTable, $formFieldsTable);	
	
			// Create main table
			createMainTable($worksheetFeed, $fieldTable, $mainTable);		
			
			// remove the google calendars
			removeCalendars($worksheetFeed, $calendarsTable, $eventsTable);					
			// Create calendar and tables
			createCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			
			// Create events table		
			createEventsTable($worksheetFeed, $eventsTable); 
			
			// Create Users table		
			createUsersTable($worksheetFeed, $usersTable, $calendarsTable);
		}
		if ($command == "calendars") { // recreate calendars and events 
			// remove the google calendars
			removeCalendars($worksheetFeed, $calendarsTable, $eventsTable);					
			// Create calendar and tables
			createCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			// Create events table		
			createEventsTable($worksheetFeed, $eventsTable); 
			// Create Users table		
			createUsersTable($worksheetFeed, $usersTable, $calendarsTable);					
		}

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
			if ($type == 'TEXT' || $type == 'LIST')		// translate TEXT in the worksheet to VARCHAR(64)
				$type = 'VARCHAR(64)';
			elseif ($type == 'Hyperlink' || $type == 'EmbedHyperlink')
					$type = 'VARCHAR(256)';	// For long links
			else	
				$type = 'VARCHAR(32)'; // DATE field		
			$mainSql .= "`$ID` $type";
			$col++;
		}

		$mainSql .= ");";
		
		// echo $sql;
		$result = mysql_query($mainSql) or die('Create Main table Failed! ' . mysql_error());
		echo "Table ".$mainTable." created with ".($col)." columns<br>\n"; 		
	}
	else {
		echo " Columns not found in table" ;
	}


}

function createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable) {
		// create fieldType table
		$sql = "DROP TABLE IF EXISTS $fieldTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $fieldTable ( `name` VARCHAR(64), `index` INT(32), `type` VARCHAR(32), `input` VARCHAR(32), `searchable` VARCHAR(32), `default` VARCHAR(256));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Field Type table Failed! ' . mysql_error());
		echo "Table ".$fieldTable." created<br>\n"; 
		
		// create listValue table
		$sql = "DROP TABLE IF EXISTS $listValueTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $listValueTable (`index` INT(32), `value` VARCHAR(64));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create listValue table Failed! ' . mysql_error());
		echo "Table ".$listValueTable." created<br>\n"; 
		
		$worksheet = $worksheetFeed->getByTitle('CellType');
		$cellFeed = $worksheet->getCellFeed();

		$row = 2;
		$cellEntry = $cellFeed->getCell($row, 1);	
		while ($cellEntry && ($name = $cellEntry->getContent()) != "") {
			$col = 2;
			$cellEntry = $cellFeed->getCell($row, $col++);	
			$type = $cellEntry->getContent();
 
			$cellEntry = $cellFeed->getCell($row, $col++);	
			if (!$cellEntry)
				$input = 'N';
			else
				$input = $cellEntry->getContent();

			$cellEntry = $cellFeed->getCell($row, $col++);
			if (!$cellEntry)
				$searchable = 'N';
			else
				$searchable = $cellEntry->getContent();

			$cellEntry = $cellFeed->getCell($row, $col++);
			if ($cellEntry)
				$default = $cellEntry->getContent();
			else 
				$default = "";
							
			$sql = "INSERT INTO $fieldTable VALUES ('$name', '$row', '$type', '$input', '$searchable', '$default');";
					// echo $sql;
			$result = mysql_query($sql) or die('Insert to fields table Failed! ' . mysql_error());

			if ($type == "LIST") {
				// Add list values to list table

				$cellEntry = $cellFeed->getCell($row, $col);	
				while ($cellEntry && ($value = $cellEntry->getContent()) != "") {
					$value = mysql_real_escape_string($value);
					$sql = "INSERT INTO $listValueTable VALUES ('$row', '$value');";
							// echo $sql;
					$result = mysql_query($sql) or die('Insert to list values table Failed! ' . mysql_error());
					$cellEntry = $cellFeed->getCell($row, ++$col);	
				}			
			}
			$cellEntry = $cellFeed->getCell(++$row, 1);
		}
}

function createFormTables($worksheetFeed, $formsTable, $formFieldsTable) {
		// Create Form table	
		$sql = "DROP TABLE IF EXISTS $formsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		
		$sql = "CREATE TABLE $formsTable ( title VARCHAR(64), number INT(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Forms table Failed! ' . mysql_error());
	
		echo "Table ".$formsTable." created<br>\n"; 

		$sql = "DROP TABLE IF EXISTS $formFieldsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $formFieldsTable ( formNumber INT(32), fieldIndex INT(32), fieldType VARCHAR(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create fields table Failed! ' . mysql_error());
		
		echo "Table ".$formFieldsTable." created<br>\n"; 
		
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
			echo "Table ".$title." inserted to forms table<br>\n"; 
			
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


function removeCalendars($worksheetFeed, $calendarsTable, $eventsTable) {
		// First remove the existing Google calendars
		echo "Removing calendars...<br>\n\n";
		$sql = "SHOW TABLES LIKE '$calendarsTable';";		
		$result = mysql_query($sql) or die('Find calendars table Failed! ' . mysql_error());
		if (mysql_num_rows($result) > 0)	{
			// calendars table exists
			$failed = false;
			$calendars = [];
			$sql = "SELECT * FROM $calendarsTable;";			
			$res = mysql_query($sql) or die('Get calendars Failed! ' . mysql_error());	
			while ($cal = mysql_fetch_array($res)) {
				$calID = $cal["calID"];
				$calNum = $cal["number"];
				if (!array_search($calID, $calendars)) {
					if (deleteCalendar($calID)) {
						// Empty the calendars table
						$sql = "DELETE FROM $calendarsTable WHERE calID='$calID';";
						$result = mysql_query($sql) or die('DELETE calendar from DB Failed! ' . mysql_error());
						// Empty the events table
						$sql = "DELETE FROM $eventsTable WHERE calendarID='$calNum';";
						$result = mysql_query($sql) or die('DELETE events Failed! ' . mysql_error());					
						
						// add to calendars list if not already there
						array_push($calendars, $calID);	
					}
					else {
						echo "Deletion of Google calendar ID: ".$calID. " Failed!<br>\n";
						$failed = true;
					}
				}
			}
			if ($failed)
					die("Removing calendars failed !"); 
		
		}


}


function createCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable) {

		// Drop the calendars table
		$sql = "DROP TABLE IF EXISTS $calendarsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
	
		// Create Calendars table		
		$sql = "CREATE TABLE $calendarsTable ( number INT(32), name VARCHAR(64), fieldIndex INT(32), formNumber INT(32), titleField INT(32) , locationField INT(32), calID VARCHAR(128));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Calendars table Failed! ' . mysql_error());
	
		echo "Table ".$calendarsTable." created<br>\n"; 
		
		// Read the calendars worksheet
		$worksheet = $worksheetFeed->getByTitle('Calendars');
		$cellFeed = $worksheet->getCellFeed();
		
		$row = 2;
		$calNumber = 0;
		$calID = 0;
		$prevCalName = "";
		// loop on rows = calendars
		$cellEntry = $cellFeed->getCell($row, 1);		
		while ($cellEntry && ($calendarName = $cellEntry->getContent()) != "") {
			if ($calendarName != $prevCalName) {
				// a new calendar
				$calID = 0;
			}
			$prevCalName = $calendarName;
			$calNumber++;

			// go over columns to get the details per calendar
			$col = 2;
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$fieldName = $fieldCell->getContent();
			else
				$fieldName = 0;

			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$formName = $fieldCell->getContent();
			else
				$formName = 0;

			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$eventTitleFieldName = $fieldCell->getContent();
			else
				$eventTitleFieldName = 0;

			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$locationFieldName = $fieldCell->getContent();
			else
				$locationFieldName = 0;

			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$colorFieldName = $fieldCell->getContent();
			else 
				$colorFieldName = 0;	

			// find the key field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$fieldName';";			
			$result = mysql_query($sql) or die('Get field index Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$fieldIndex = $field["index"];
			else
				die('Failed to find field: '.$fieldName);

			// find the form ID
			$sql = "SELECT * FROM $formsTable WHERE title='$formName';";			
			$result = mysql_query($sql) or die('Get form Failed! ' . mysql_error());	
			if ($form = mysql_fetch_array($result))
				$formID = $form["number"];
			else				
				die('Failed to find form: '.$formName);

			// find the title field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$eventTitleFieldName';";			
			$result = mysql_query($sql) or die('Get title field Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$titleFieldIndex = $field["index"];	
			else				
				die('Failed to find field: '.$eventTitleFieldName);

			// find the location field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$locationFieldName';";			
			$result = mysql_query($sql) or die('Get location field Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$locationFieldIndex = $field["index"];
			else				
				die('Failed to find field: '.$locationFieldName);


			if (!$calID) {
				// new calendar - create it and add to the table
				$calID = createCalendar($worksheetFeed, $calendarName);	
			}
			if ($calID) {
				// add it to the table
				$sql = "INSERT INTO $calendarsTable VALUES ('$calNumber', '$calendarName', '$fieldIndex', '$formID', '$titleFieldIndex', '$locationFieldIndex', '$calID');";
				$result = mysql_query($sql) or die('Insert calendar Failed! ' . mysql_error());
			}

			$cellEntry = $cellFeed->getCell(++$row, 1);		
		}

}	

function createUsersTable($worksheetFeed, $usersTable, $calendarsTable) {

		$sql = "DROP TABLE IF EXISTS $usersTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $usersTable ( userName VARCHAR(64), email VARCHAR(64), calendarNum INT(32));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Users table Failed! ' . mysql_error());
		
		echo "Table ".$usersTable." created<br>\n"; 
	
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
		$sql = "CREATE TABLE $eventsTable ( calendarID INT(32), orderID INT(32), eventDate DATETIME, eventID VARCHAR(128), updated TINYINT);";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Events table Failed! ' . mysql_error());
		
		echo "Table ".$eventsTable." created<br>\n"; 

}




function updateListValueTable($worksheetFeed, $listValueTable) {
		// create listValue table
		$sql = "DROP TABLE IF EXISTS $listValueTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $listValueTable (`index` INT(32), `value` VARCHAR(64));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create listValue table Failed! ' . mysql_error());
		echo "Table ".$listValueTable." created<br>\n"; 
		
		$worksheet = $worksheetFeed->getByTitle('CellType');
		$cellFeed = $worksheet->getCellFeed();

		$row = 2;
		$cellEntry = $cellFeed->getCell($row, 1);	
		while ($cellEntry && ($name = $cellEntry->getContent()) != "") {
			$cellEntry = $cellFeed->getCell($row, 2);	
			$type = $cellEntry->getContent();
 
			$cellEntry = $cellFeed->getCell($row, 3);	
			$input = $cellEntry->getContent();
			
			$cellEntry = $cellFeed->getCell($row, 4);
			if ($cellEntry)
				$default = $cellEntry->getContent();
			else 
				$default = "";

			if ($type == "LIST") {
				// Add list values to list table
				$col = 5;	
				$cellEntry = $cellFeed->getCell($row, $col);	
				while ($cellEntry && ($value = $cellEntry->getContent()) != "") {
					$value = mysql_real_escape_string($value);
					$sql = "INSERT INTO $listValueTable VALUES ('$row', '$value');";
							// echo $sql;
					$result = mysql_query($sql) or die('Insert to list values table Failed! ' . mysql_error());
					$cellEntry = $cellFeed->getCell($row, ++$col);	
				}			
			}
			$cellEntry = $cellFeed->getCell(++$row, 1);
		}
}



function updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable) {
		// Just add new defined calendars
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
					// Check if calendar exist
					$sql = "SELECT * FROM $calendarsTable WHERE number ='$calNumber' AND name='$name' AND fieldIndex='$row' AND filter='$filter';";
					$result = mysql_query($sql) or die('Select calendar Failed! ' . mysql_error());
					if (mysql_num_rows($result) == 0) {
						// calendar doesn't exist - create it and add to the table
						if (!$calID)
							$calID = createCalendar($worksheetFeed, $name);
						
						// add it to calendar table
						$sql = "INSERT INTO $calendarsTable VALUES ('$calNumber', '$name', '$row', '$filter', 0, 0, 0, 0, '$calID');";
						$result = mysql_query($sql) or die('Insert calendar Failed! ' . mysql_error());
					}
					
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


?>
