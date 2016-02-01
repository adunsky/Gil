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
require_once "cfgUtils.php";

require realpath(dirname(__FILE__) . '/php-google-spreadsheet-client-master/vendor/autoload.php');

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;


		$command = $_GET['cmd'];
		echo "Command: ".$command."<br>\n";
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
			
		setSSName($ssName);
 		set_time_limit (0); // This may take a while
		$spreadsheet = initGoogleAPI($ssName); // from spreadsheet.php
		$worksheetFeed = $spreadsheet->getWorksheets();

		syslog(LOG_INFO, "Running updateDB, command: ".$command);
		if ($command == "updateLists") {
			// just update the list values
			updateListValueTable($worksheetFeed, $listValueTable);

		}
		if ($command == "fields") {		
			// Create FieldType table
			updateFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
			// field changes may impact emailCfg table
			createEmailCfgTable($worksheetFeed);
		}
		if ($command == "forms") {	
			// Create form and field form tables	
			updateFormTables($worksheetFeed, false);	
		}		
		if ($command == "calendars") {
			// update calendars and users that share them
			updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			updateUsersTable($worksheetFeed);	

		}
		if ($command == "users") {
			// Just update the Users table		
			updateUsersTable($worksheetFeed);	

		}

		if ($command == "cleanEmails") {
			// update email configuration and remove all existing emails
			createEmailCfgTable($worksheetFeed);
			createEmailsTable($worksheetFeed);
		}

		if ($command == "emails") {
			// update email configuration but keep existing emails
			createEmailCfgTable($worksheetFeed);
		}

		if ($command == "search") {
			// update email configuration but keep existing emails
			createSearchTables($worksheetFeed);
		}
				
		if ($command == "all") {		
			// Create FieldType table
			updateFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
		
			// Create form and field form tables	
			updateFormTables($worksheetFeed, false);	
			
			updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable);
			
			// Create Users table		
			updateUsersTable($worksheetFeed);

			createEmailCfgTable($worksheetFeed);
			createSearchTables($worksheetFeed);
		}


function updateFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable) {
		global $mainTable, $dbName ;
	
		// get the current number of fields in case we need to add to Main
		$sql = "SELECT * FROM $mainTable;";
		$result = mysql_query($sql) or die('Select Main table Failed! ' . mysql_error());
		$fieldsCount = mysql_num_fields($result)-1; // subtruct the id field
		
		// create fieldType table
		$sql = "DROP TABLE IF EXISTS $fieldTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $fieldTable ( `name` VARCHAR(64), `index` INT(32), `type` VARCHAR(32), `input` VARCHAR(32), `searchable` VARCHAR(32), `default` VARCHAR(256));";
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
					
			echo "Field added: $name, $row, $type, $input, $searchable, $default <br>";				
			$sql = "INSERT INTO $fieldTable VALUES ('$name', '$row', '$type', '$input', '$searchable', '$default');";
					// echo $sql;
			$result = mysql_query($sql) or die('Insert to fields table Failed! ' . mysql_error());

			if ($type == 'TEXT' || $type == 'LIST' || $type == 'CHARGE')		// translate TEXT in the worksheet to VARCHAR(64)
				$DBtype = 'varchar(64)';
			elseif ($type == 'Hyperlink' || $type == 'EmbedHyperlink')
					$DBtype = 'varchar(256)';	// For long links
			else	
				$DBtype = 'varchar(32)'; // DATE field		

			if ($fieldsCount && $row-1 > $fieldsCount) { // new fields added
				echo "Adding column: ".$name." to Main table <br>\n";
				// add the extra columns to the Main table	
				$sql = "ALTER TABLE $mainTable ADD `$row` $DBtype;";
				$result = mysql_query($sql) or die('Add column to main table Failed! ' . mysql_error());
			}

			// update the field type if needed
			$result = mysql_query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND table_name = '$mainTable' AND COLUMN_NAME = '$row'");
			if (!$result) {
				die('Select column from main table Failed! ' . mysql_error());
			}
			else {
				$resArray = mysql_fetch_array($result);
				$currType = $resArray["DATA_TYPE"]."(".$resArray["CHARACTER_MAXIMUM_LENGTH"].")";
				if ($currType != $DBtype){
					echo "Updating column type: ".$name." to ".$DBtype."<br>\n";
					$result = mysql_query("ALTER TABLE $mainTable MODIFY COLUMN `$row` $DBtype");
					if (!$result)
						die('Change column type in main table Failed! ' . mysql_error());					
				}
			}

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


function updateCalndarsTable($worksheetFeed, $calendarsTable, $formsTable, $fieldTable) {
		// add new calendars at the end of the list or update form, title, location fields
		// Read the calendars worksheet
		$worksheet = $worksheetFeed->getByTitle('Calendars');
		$cellFeed = $worksheet->getCellFeed();
		
		// get the current number of calendars
		$result = mysql_query("SELECT MAX(number) AS num FROM $calendarsTable");
		if ($rec = mysql_fetch_array($result))
			$maxCal = $rec["num"];
		else
			$maxCal = 0;

		$count = 0;
		$row = 2;
		$calNumber = 0;
		$calID = 0;
		// loop on rows = calendars
		$cellEntry = $cellFeed->getCell($row, 1);		
		while ($cellEntry && ($calendarName = $cellEntry->getContent()) != "") {
			$calNumber++;

			// go over columns to get the details per calendar
			$col = 2;
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$fieldName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$formName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$eventTitleFieldName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$locationFieldName = $fieldCell->getContent();
			$fieldCell = $cellFeed->getCell($row, $col++);
			if ($fieldCell)
				$participantsField = $fieldCell->getContent();
			else 
				$participantsField = 0;	

			// find the key field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$fieldName';";			
			$result = mysql_query($sql) or die('Get field index Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$fieldIndex = $field["index"];
			else
				die('Failed to find field index for calendar:'.$calendarName);

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

			// find the participants field index	
			$sql = "SELECT * FROM $fieldTable WHERE name='$participantsField';";			
			$result = mysql_query($sql) or die('Get participants field Failed! ' . mysql_error());	
			if ($field = mysql_fetch_array($result))
				$participantsFieldIndex = $field["index"];
			else				
				$participantsFieldIndex = 0;

			// Check if calendar exist
			$sql = "SELECT * FROM $calendarsTable WHERE name='$calendarName' AND fieldIndex='$fieldIndex';";
			$result = mysql_query($sql) or die('Select calendar Failed! ' . mysql_error());
			if (mysql_num_rows($result) == 0) {
				// it is a new calendar in the table
				$maxCal++;
				echo "Adding calendar: ".$maxCal." ".$calendarName."<br>\n";
				// Check if calendar with the same name exists - same google calendar
				$sql = "SELECT * FROM $calendarsTable WHERE name='$calendarName';";
				$result = mysql_query($sql) or die('Select calendar Failed! ' . mysql_error());
				if ($existingCal = mysql_fetch_array($result)) {
					// it is an existing google calendar
					$calID = $existingCal["calID"];
					$count = $existingCal["count"];
				}
				else {
					// calendar doesn't exist - create it and add to the table
					$count++;
					$calID = createCalendar($worksheetFeed, $calendarName, $count);
				}
				if ($calID) {
					// insert it as the last calendar number as we can't change existing cal numbers
					$sql = "INSERT INTO $calendarsTable VALUES ('$maxCal', '$count', '$calendarName', '$fieldIndex', '$formID', '$titleFieldIndex', '$locationFieldIndex', '$participantsFieldIndex', '$calID');";
					if (!$result = mysql_query($sql)) {
						deleteCalendar($calID, $count);
						die('Insert calendar Failed! ' . mysql_error());
					} 
				}
			}				
			else {
				// calendar exist - update it
				$existingCal = mysql_fetch_array($result);
				$count = $existingCal["count"];
				echo "Updating calendar: ".$calNumber." ".$calendarName."<br>\n";
				$sql = "UPDATE $calendarsTable SET formNumber='$formID', titleField='$titleFieldIndex', locationField='$locationFieldIndex', participants='$participantsFieldIndex' WHERE name='$calendarName' AND fieldIndex='$fieldIndex';";
				$result = mysql_query($sql) or die('Update calendar Failed! ' . mysql_error());
			}
			$cellEntry = $cellFeed->getCell(++$row, 1);		
		}

}	


?>
