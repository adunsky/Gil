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

session_start();
   
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
	global $theSpreadsheet;
	
	$theSpreadsheet = $ssName;

} 
 
 
function getClientSS($DBname) {
		global $globalDBName;

		$currentDB = $globalDBName; // save current DB
		if (selectDB("customers")) {
	      $sql =  "SELECT * FROM customers WHERE dbName='$DBname';";
	      $result = mysql_query($sql);
      	$customer = mysql_fetch_array($result);
      	$ssName = $customer["ssName"];
      	if ($currentDB && $currentDB != "")
      		selectDB($currentDB); // set it back to original	
      	return $ssName;
		}	
		return NULL;
} 
 
function initGoogleAPI($spreadsheetName = NULL) {
	global $theSpreadsheet, $spreadsheet;
		
	if (!$spreadsheetName)
		$spreadsheetName = $theSpreadsheet;
		
	//echo 	"Spreadsheet name: ".$spreadsheetName."<br>\n";
	syslog (LOG_INFO, "Spreadsheet name: ".$spreadsheetName."<br>\n");
	if (isset($_SESSION["spreadsheet"] ) && false) { // need to serialize it
		syslog(LOG_INFO, "spreadsheet found");
		$spreadsheetService = $_SESSION["spreadsheet"];	
	}
	else {
	
		$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
		$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
		$clientkeypath = 'API Project-0ffd21d566b5.p12';
		
		try {
			$client = new Google_Client();
			$client->setApplicationName("Gilamos");
			$client->setClientId($clientid);	 
			
			if (isset($_SESSION['service_token'])) {
			  $client->setAccessToken($_SESSION['service_token']);
			}
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
				$_SESSION['service_token'] = $client->getAccessToken();
				
				$obj_token  = json_decode($client->getAccessToken());
				$accessToken = $obj_token->access_token;
				  	
				//var_dump($accessToken);
				
				$serviceRequest = new DefaultServiceRequest($accessToken);
				ServiceRequestFactory::setInstance($serviceRequest);
				
				$spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
				$_SESSION["spreadsheet"] = $spreadsheetService;		
			}
			else {
				syslog(LOG_ERR, "could not access spreadsheet");
				return null;
			} 
		}
		//catch exception
		catch(Exception $e) {
			echo 'Exception: ' .$e->getMessage(). "<br>";
			return null;	
		}
	}
	syslog(LOG_INFO, "service initiated<br>");
	$spreadsheetFeed = $spreadsheetService->getSpreadsheets();
	$spreadsheet = $spreadsheetFeed->getByTitle($spreadsheetName);


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


function getCalcFields($order) {
	global $spreadsheet;	
	set_time_limit (60); // This may take a while
	date_default_timezone_set("Asia/Jerusalem");
	$profile = false;
	
	if ($profile) {
		$currTime = date("h:i:s");
		syslog(LOG_INFO, " Start SS init : ".$currTime);
	} 
	//$spreadsheet = initGoogleAPI();
	if ($spreadsheet == null)
		return null;
	try {	
		$worksheetFeed = $spreadsheet->getWorksheets();
		$worksheet = $worksheetFeed->getByTitle('Main');
		$cellFeed = $worksheet->getCellFeed();
		
		// write order to spreadsheet
		if ($profile) {	
			$currTime = date("h:i:s");
			syslog(LOG_INFO, " Start SS write : ".$currTime); 
		}
		
	   while (!($locked = get_named_lock('mylock'))) { // wait until unlocked
	   	//echo "Waiting for spreadsheet to unlock <br>\n";
	   	syslog(LOG_INFO, "Waiting for spreadsheet to unlock ");
	   }
	
	
		// batch update
		$batchRequest = new Google\Spreadsheet\Batch\BatchRequest();
		
		foreach ($order as $field) {
			$col = $field["index"];
			$value = $field["value"];
			$isInput = $field["input"] == 'Y';
			$type = $field["type"];
	
			if ($isInput) { // it is an input field
					if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
						$type = "DATETIME";  // it behaves like DATETIME
					if (($type == "DATE" || $type == "DATETIME") && $value!="") {
							// remove the time from the datetime field
						if ($date = strtotime($value)) {
							syslog(LOG_INFO, "Value before date: ".$value."\n");
							if ($type == "DATE")
								$value = date('d-m-Y', $date);
							else { // DATETIME
								$value = date('d-m-Y H:i:s', $date);
							}
							syslog(LOG_INFO, "After date: ".$value."\n");						
						}
						else	
							$value = "";  // clear invalid date
	 				}
	 				if ($value == "")
	 					$value = "_none"; // ensure non empty cells for the batch to work
	 				else
						$value = str_replace('"','', $value);	
					$input = $cellFeed->getCell(2, $col);
					if (empty($input)) {
	        			// CellEntry doesn't exist. Use edit cell.
	        			$cellFeed->editCell(2, $col, $value);
	        		}
	        		else {	
	 					$input->setContent($value);
		        		$batchRequest->addEntry($input);
				 	//echo "Value: ".$value."<br>\n";
	        		}
			}		
				
		}	
		$batchRes = $cellFeed->insertBatch($batchRequest);	
		if ($batchRes->hasErrors())
			syslog(LOG_ERR, "Error in batch<br>\n");	
		if ($profile) {
			$currTime = date("h:i:s");
			syslog (LOG_INFO, " End SS write : ".$currTime); 
		}	
		//sleep(5);
			// now get the computed values
		$listFeed = $worksheet->getListFeed();
		
		$entries = $listFeed->getEntries();
		$listEntry = $entries[0]; 
		$values = $listEntry->getValues();
		//var_dump($values);
	
	
		release_named_lock('mylock');
		
	
		$i = -1;
		foreach ($values as $value) { // update the output values
			if ($i > -1) {// skip the first column
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
	
			if (!array_key_exists(++$i, $order))
				break;	// reached the end of the array
		}
		if ($profile) {	
			$currTime = date("h:i:s");
			syslog (LOG_INFO, " End SS read : ".$currTime."\n"); 
		}
	}
	//catch exception
	catch(Exception $e) {
		echo 'Exception: ' .$e->getMessage(). "<br>";
		return null;	
	}
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
?>
