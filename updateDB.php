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
		createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable);
	
		// Create form and field form tables	
		createFormTables($worksheetFeed, $formsTable, $formFieldsTable);	
		
		// Create Users table		
		createUsersTable($worksheetFeed, $usersTable, $calendarsTable);



function createFieldTypeTable($worksheetFeed, $fieldTable, $listValueTable) {
		// create fieldType table
		$sql = "DROP TABLE IF EXISTS $fieldTable;";
		$result = mysql_query($sql) or die('Drop table Failed! ' . mysql_error());
		$sql = "CREATE TABLE $fieldTable ( `name` VARCHAR(64), `index` INT(32), `type` VARCHAR(32), `input` VARCHAR(32), `default` VARCHAR(32));";
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
