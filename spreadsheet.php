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
session_start();
require_once "google-api-php-client-master/autoload.php";

require_once "mydb.php";


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
 
function initGoogleAPI() {
	if (isset($_SESSION["spreadsheet"] ) && false) { // need to serialize it
		echo "spreadsheet found";
		$spreadsheetService = $_SESSION["spreadsheet"];	
	}
	else {
	
		$spreadsheetName = 'Take3';
		$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
		$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
		$clientkeypath = 'API Project-0ffd21d566b5.p12';
		
		$client = new Google_Client();
		$client->setApplicationName("gil");
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
			echo "could not access spreadsheet";
			return null;
		} 
	}
	//echo "service initiated<br>";
	$spreadsheetFeed = $spreadsheetService->getSpreadsheets();
	$spreadsheet = $spreadsheetFeed->getByTitle($spreadsheetName);

	return $spreadsheet;

	
}

function getCalcFields($order) {	
	set_time_limit (0); // This may take a while
	date_default_timezone_set("Asia/Jerusalem");
	$profile = false;
	
	if ($profile) {
		$currTime = date("h:i:s");
		echo " Start SS init : ".$currTime;
	} 
	$spreadsheet = initGoogleAPI();
	
	$worksheetFeed = $spreadsheet->getWorksheets();
	$worksheet = $worksheetFeed->getByTitle('Main');
	$cellFeed = $worksheet->getCellFeed();
	
	// write order to spreadsheet
	if ($profile) {	
		$currTime = date("h:i:s");
		echo " Start SS write : ".$currTime; 
	}	
	// batch update
	$batchRequest = new Google\Spreadsheet\Batch\BatchRequest();
	
	foreach ($order as $field) {
		$col = $field["index"];
		$value = $field["value"];
		$isInput = $field["input"] == 'Y';
		$type = $field["type"];

		if ($isInput) { // it is an input field
				if ($type == "DATE" && $value!=" ") {
						// remove the time from the datetime field
					if ($value != "" && $date = strtotime($value)) {
						//echo "before date: ".$value."\n";	
						$value = date('d-m-Y', $date);
						//echo "After date: ".$value."\n";						
					}
					else 
						$value = "";  // clear invalid date					
						// echo "date: ".$value."\n";			
				}		
 
 				if ($value == "")
 					$value = "_none"; // ensure non empty cells for the batch to work
				$input = $cellFeed->getCell(2, $col);
				if (empty($input)) {
        			// CellEntry doesn't exist. Use edit cell.
        			$cellFeed->editCell(2, $col, $value);
        		}
        		else {	
					$input->setContent($value);
	        		$batchRequest->addEntry($input);
        	}
		}		
			
	}	
	$cellFeed->updateBatch($batchRequest);	
	
/*	
	// list update	
	$listFeed = $worksheet->getListFeed();
	
	$entries = $listFeed->getEntries();
	$listEntry = $entries[0];	
	$values = $listEntry->getValues();
	
	$keys = array_keys($values);
	$i=1;
	foreach ($order as $field) {
		$values[$keys[$i++]] = $field["value"];
			
	}	
	$listEntry = $entries[1];	
	$listEntry->update($values);	
	
	
*/
	if ($profile) {
		$currTime = date("h:i:s");
		echo " End SS write : ".$currTime; 
	}	
		// now get the computed values
	$listFeed = $worksheet->getListFeed();
	
	$entries = $listFeed->getEntries();
	$listEntry = $entries[0]; 
	$values = $listEntry->getValues();
	//var_dump($values);
	$i = -1;
	foreach ($values as $value) { // update the output values
		if ($i > -1) {// skip the first column
			if ($value == "_none")
				$order[$i]["value"] = "";
			else	
				$order[$i]["value"] = $value;
		}
		$i++;
	}
	if ($profile) {	
		$currTime = date("h:i:s");
		echo " End SS read : ".$currTime."\n"; 
	}
	return $order;
}

?>
