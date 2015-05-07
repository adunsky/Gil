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


		$command = $_GET['cmd'];
		echo "Command: ".$command."<br>\n";
		$dbName = $_GET['db'];
		//echo $dbName;
				
		if (!selectDB($dbName))
			return;	
			
		$ssName = getClientSS($dbName);
		if ($ssName)
			setSSName($ssName);
		else {
			echo "spreadsheet not found for DB: ".$dbName;
			return;		
		}					
			
		setSSName($ssName);
 		set_time_limit (0); // This may take a while
		$spreadsheet = initGoogleAPI($ssName); // from spreadsheet.php
		$worksheetFeed = $spreadsheet->getWorksheets();

		if ($command == "updateLists") {
			// just update the list values
			updateListValueTable($worksheetFeed, $listValueTable);

		}
		if ($command == "fields") {		
			// Create FieldType table
			createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
		}
		if ($command == "forms") {	
			// Create form and field form tables	
			createFormTables($worksheetFeed, $formsTable, $formFieldsTable);	
		}		
		if ($command == "calendars") {
			// update calendars and users that share them
			updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			createUsersTable($worksheetFeed, $usersTable, $calendarsTable);	

		}
		if ($command == "users") {
			// Just update the Users table		
			createUsersTable($worksheetFeed, $usersTable, $calendarsTable);	

		}
				
		if ($command == "all") {		
			// Create FieldType table
			createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
		
			// Create form and field form tables	
			createFormTables($worksheetFeed, $formsTable, $formFieldsTable);	
			
			updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			
			// Create Users table		
			createUsersTable($worksheetFeed, $usersTable, $calendarsTable);
		}


function updateListValueTable($worksheetFeed, $listValueTable) {
		// create listValue table
		$sql = "DROP TABLE IF EXISTS $listValueTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $listValueTable (`index` INT(32), `value` VARCHAR(64));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create listValue table Failed! ' . mysql_error());
		echo "Table ".$listValueTable." created<br>"; 
		
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


function createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable) {
		// create fieldType table
		$sql = "DROP TABLE IF EXISTS $fieldTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $fieldTable ( `name` VARCHAR(64), `index` INT(32), `type` VARCHAR(32), `input` VARCHAR(32), `default` VARCHAR(256));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create Field Type table Failed! ' . mysql_error());
		echo "Table ".$fieldTable." created<br>"; 
		
		// create listValue table
		$sql = "DROP TABLE IF EXISTS $listValueTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $listValueTable (`index` INT(32), `value` VARCHAR(64));";
				// echo $sql;
		$result = mysql_query($sql) or die('Create listValue table Failed! ' . mysql_error());
		echo "Table ".$listValueTable." created<br>"; 
		
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
							
			$sql = "INSERT INTO $fieldTable VALUES ('$name', '$row', '$type', '$input', '$default');";
					// echo $sql;
			$result = mysql_query($sql) or die('Insert to fields table Failed! ' . mysql_error());

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

function createFormTables($worksheetFeed, $formsTable, $formFieldsTable) {
		// Create Form table	
		$sql = "DROP TABLE IF EXISTS $formsTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		
		$sql = "CREATE TABLE $formsTable ( title VARCHAR(64), number INT(32));";
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
						$sql = "INSERT INTO $calendarsTable VALUES ('$calNumber', '$name', '$row', '$filter', 0, 0, 0, '$calID');";
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


function createUsersTable($worksheetFeed, $usersTable, $calendarsTable) {

		$sql = "DROP TABLE IF EXISTS $usersTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $usersTable ( userName VARCHAR(64), email VARCHAR(64), calendarNum INT(32));";
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


?>
