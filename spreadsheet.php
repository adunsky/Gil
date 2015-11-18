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
require_once "google-api-php-client-master/autoload.php";

require_once "mydb.php";

//session_start();
   
require "php-google-spreadsheet-client-master/vendor/autoload.php";

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\Batch;



/************************************************
  If we have an access token, we can carry on.
  Otherwise, we'll get one with the help of an
  assertion credential. In other examples the list
  of scopes was managed by the Client, but here
  we have to list them manually. We also supply
  the service account
 ************************************************/

function setSSName($ssName) {
	global $mainSpreadsheetName;
	
	$mainSpreadsheetName = $ssName;

} 
 

function initGoogleAPI($spreadsheetName = NULL) {
	global $mainSpreadsheetName, $spreadsheet, $globalDBName, $appName;

	$customer = getClient($globalDBName, 1);	// always use the first client

	$clientid = $customer["clientID"];
	$clientmail = $customer["clientMail"];
	$clientkeypath = $customer["clientKeyPath"];

	if (!$spreadsheetName)
		$spreadsheetName = $mainSpreadsheetName;		// the default spreadsheet
		
	//echo 	"Spreadsheet name: ".$spreadsheetName."<br>\n";

	try {
		syslog (LOG_INFO, "Init Google service, Spreadsheet name: ".$spreadsheetName."<br>\n");

		$client = new Google_Client();
		$client->setApplicationName($appName);
		$client->setClientId($clientid);	 
		
		/*
		if (isset($_SESSION['service_token'])) {
		  $client->setAccessToken($_SESSION['service_token']);
		  syslog(LOG_INFO, "Service token is set for session");
		}
		*/
		$key = file_get_contents($clientkeypath);
		$cred = new Google_Auth_AssertionCredentials(
		    $clientmail,
		    array('https://spreadsheets.google.com/feeds',
		    		 'https://www.googleapis.com/auth/calendar'),
		    $key
		);
		$client->setAssertionCredentials($cred);
		
		
		if ($client->getAuth()->isAccessTokenExpired()) {
		  $client->getAuth()->refreshTokenWithAssertion($cred);
		}
			   
		if ($client->getAccessToken()) {
			//$_SESSION['service_token'] = $client->getAccessToken();
			
			$obj_token  = json_decode($client->getAccessToken());
			$accessToken = $obj_token->access_token;
			  	
			//var_dump($accessToken);
			
			$serviceRequest = new DefaultServiceRequest($accessToken);
			ServiceRequestFactory::setInstance($serviceRequest);
			
			$spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
	
		}
		else {
			syslog(LOG_ERR, "could not access spreadsheet");
			return null;
		} 

		$spreadsheetFeed = $spreadsheetService->getSpreadsheets();
		$spreadsheet = $spreadsheetFeed->getByTitle($spreadsheetName);
	}
	catch(Exception $e) {
		syslog (LOG_ERR, "Exception: " .$e->getMessage());
		return null;	
	}	
	syslog(LOG_INFO, "service initiated<br>");
	return $spreadsheet;
}



function get_named_lock($lockname) {
     $sql = "SELECT IS_FREE_LOCK('$lockname') AS isfree";
     $result = mysql_query($sql);
     $lock = mysql_fetch_array($result);

	  if ($lock['isfree']) {
	      $sql =  "SELECT GET_LOCK('$lockname', 1) AS locked";
	      $result = mysql_query($sql);
	      $lock = mysql_fetch_array($result);
	      return $lock['locked'];
	  } 
	  else {
	  		return false;
	  }
}

function release_named_lock($lockname) {
        $sql = "DO RELEASE_LOCK('$lockname')";
        $result = mysql_query($sql);

}


function getCalcFields($order, $oldValues) {
	global $spreadsheet, $mainSpreadsheetName, $useOldValues;	
	set_time_limit (60); // This may take a while

	//$spreadsheet = initGoogleAPI();
	if ($spreadsheet == null)
		return null;
	try {
		syslog(LOG_INFO, "Getting worksheets");	
		$worksheetFeed = $spreadsheet->getWorksheets();
		syslog(LOG_INFO, "Getting Main worksheet");	

		$mainCounter = 0;
		$locked = false;
		while (!$locked) {
			$worksheet = $worksheetFeed->getByTitle('Main'.$mainCounter);
			if (!$worksheet) {
				$mainCounter = 0;
				// tried all Main worksheets - wait until unlocked
				syslog(LOG_INFO, "Waiting for spreadsheet to unlock...");
				sleep(1);
			}
			else {
				// try to lock the worksheet
				syslog(LOG_INFO, "Trying to lock spreadsheet: Main".$mainCounter);
			    if (get_named_lock($mainSpreadsheetName.$mainCounter)) {
			    	// success locking the worksheet
			    	$locked = true;
			    }
			    else // failed to lock it - try next Main
			    	$mainCounter++;
		    }
		}

		syslog(LOG_INFO, "Getting List feed");
		$listFeed = $worksheet->getListFeed();
		
		$entries = $listFeed->getEntries();
		$listEntry = $entries[0]; 
		$values = $listEntry->getValues();
		// write order to spreadsheet		
		syslog(LOG_INFO, "Writing to spreadsheet: Main".$mainCounter);
		foreach ($order as $field) {
			$col = $field["index"];
			$value = $field["value"];
			$input = $field["input"];
			$type = $field["type"];
			$name = $field["name"];
	
			if (!is_string($value))	// ensure that value is a string
				$value = "";

			$currValue = next($values);	// get the next value 
			$currKey = key($values);

			if ($input == 'Y' || $input == 'U') { // it is an input field
					if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
						$type = "DATETIME";  // it behaves like DATETIME
					if (($type == "DATE" || $type == "DATETIME") && $value!="") {
							// remove the time from the datetime field
						if ($date = strtotime($value)) {
							//syslog(LOG_INFO, "Value before date: ".$value."\n");
							if ($type == "DATE")
								$value = date('d-m-Y', $date);
							else { // DATETIME
								$value = date('d-m-Y H:i:s', $date);
							}
							//syslog(LOG_INFO, "After date: ".$value."\n");						
						}
						else	
							$value = "";  // clear invalid date
	 				}
	 				
	 				else // not a date
	 					if ($value != "") { // text value - add ' to ensure that the spreadsheet doesn't change it to date
	 						$value = "'".$value;

	 					}

	 				if ($value != "")
						$value = str_replace('"','', $value);	// remove qoutes from values

	        		$values[$currKey] = $value;
			}
			else
				$values[$currKey] = $value;


			//echo $name.": ".$value."\n";

		}
		$listEntry->update($values);

		if ($useOldValues) {
			syslog(LOG_INFO, "writing old values...");
			reset($values);
			$key = key($values);
			$values[$key] = "old";
			foreach ($oldValues as $field) {
				$currValue = next($values);	// get the next value 
				$key = key($values);
				$values[$key] = $field["value"];
			}
			$oldEntry = $entries[1];
			$oldEntry->update($values);
			//var_dump($values);
		}
		// now get the computed values
		syslog(LOG_INFO, "Done writing...");

		// Must recall the feeds to get the calculated values
		syslog(LOG_INFO, "Getting list feed...");		
		$listFeed = $worksheet->getListFeed();
		
		$entries = $listFeed->getEntries();
		$listEntry = $entries[2]; 
		$values = $listEntry->getValues();
	
		release_named_lock($mainSpreadsheetName);
		$i = 0;
		syslog(LOG_INFO, "Reading from spreadsheet...");
		foreach ($order as $field) { // update the output values
			$col = $field["index"];
			$input = $field["input"];
			$type = $field["type"];

			$value = next($values);

			if ($input != 'Y' && $input != 'U') { // it is an output field

				if ($value == "#N/A")
					syslog(LOG_ERR, "Error: ".$i." : ".$value);
				if ($value == "_none")
					$order[$i]["value"] = "";
				else {	
					$type = $order[$i]["type"];
					if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
						$type = "DATETIME";  // it behaves like DATETIME				
					
					if ($type == "DATE" || $type == "DATETIME")
						// format the returned date
						$value = str_replace('/', '-', $value);
	
					$order[$i]["value"] = $value;
				}
			}
			$i++;
		}

	}	// try
	//catch exception
	catch(Exception $e) {
		release_named_lock($mainSpreadsheetName);
		syslog (LOG_ERR, "Exception: " .$e->getMessage());
		return null;	
	}
	syslog(LOG_INFO, "Read spreadsheet done");
	return $order;
}



function importOrders($spreadsheetName) {
	echo "Reading ".$spreadsheetName."...<br>\n";
	$spreadsheet = initGoogleAPI($spreadsheetName);
	
	$worksheetFeed = $spreadsheet->getWorksheets();
	$worksheet = $worksheetFeed->getByTitle('UploadData');
	
	$listFeed = $worksheet->getListFeed();
	
	$entries = $listFeed->getEntries();
	
	$orders = [];
	$i = -1;
	while (array_key_exists(++$i, $entries)) {
		$listEntry = $entries[$i];
		$order = $listEntry->getValues(); 
		//var_dump($order);
		$orders[$i] = $order;
	}
	
	echo "Completed reading ".$i." records<br>\n";
	return $orders;
}

function getFirstWorksheet($spreadsheetName) {

	for ($i=0; $i<5; $i++) {	// retry 5 times
		$spreadsheet = initGoogleAPI($spreadsheetName);
		if (!$spreadsheet) {
			syslog(LOG_ERR, "Backup file: ".$spreadsheetName." doesn't exist");
			return null;
		}
		
		$worksheetFeed = $spreadsheet->getWorksheets();
		//var_dump($worksheetFeed[0]);

		if ($worksheet = $worksheetFeed[0])	// if the first worksheeth is not null
			return $worksheet;	
	}
}


function writeBackup($spreadsheetName) {
 	global $mainTable, $fieldTable;

 	date_default_timezone_set("Asia/Jerusalem");
	syslog(LOG_INFO, "Writing backup to ".$spreadsheetName."...");

	$worksheet = getFirstWorksheet($spreadsheetName);
	if (!$worksheet) {
		syslog(LOG_ERR, "Failed to get backup worksheet");
		return;
	}
	$listFeed = $worksheet->getListFeed();
	$entries = $listFeed->getEntries();

	$rows = count($entries)-1;
	syslog(LOG_INFO, "deleting ". $rows. " rows");

	for ($i=$rows ; $i>=0 ; $i--) {
		try {
			$entries[$i]->delete();
		}
		catch(Exception $e) {
			syslog (LOG_ERR, "Exception: " .$e->getMessage());
			$worksheet = getFirstWorksheet($spreadsheetName);
			$listFeed = $worksheet->getListFeed();
			$entries = $listFeed->getEntries();
			$i++;	
		}
	}


	$sql = "SELECT * FROM $mainTable";
    $result = mysql_query($sql);
    $row = [];
    $field = [];

    $sql = "Select * FROM $fieldTable";
    $res = mysql_query($sql);
    for ($i=0; $field[$i] = mysql_fetch_array($res); $i++);

	syslog(LOG_INFO, "writing backup to ". $spreadsheetName);

    while ($record = mysql_fetch_array($result)) {
    	for ($i=0; array_key_exists($i, $field); $i++) {
    		//$key = $field[$i]["name"];
    		$key = "col".$i;
    		$row[$key] = $record[$i];
    	}
    	while (true) {
	    	//var_dump($row);
	    	$error = false;
	    	try {
	     		$listFeed->insert($row); 
	     		break;
	     	}
	     	catch(Exception $e) {
	     		syslog (LOG_ERR, "Exception: " .$e->getMessage());
	     		$worksheet = getFirstWorksheet($spreadsheetName);
	     		$listFeed = $worksheet->getListFeed();
	     	}
     	}
    }


    $worksheet->update(date("d/m/Y H:i"));

	syslog(LOG_INFO, "Completed writing backup to ".$spreadsheetName."...");
	
}


?>
