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

require realpath(dirname(__FILE__) . '/php-google-spreadsheet-client-master/vendor/autoload.php');

use Google\Drive\DefaultServiceRequest;
use Google\Drive\ServiceRequestFactory;

		if (!$_GET)
			// get arguments from command line		
			parse_str(implode('&', array_slice($argv, 1)), $_GET);

		$dbName = $_GET['db'];
		//echo $dbName;
		$time = $_GET['time'];
				
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
 		date_default_timezone_set("Asia/Jerusalem");

 		$targetTime = strtotime($time);

 		while (true) {
 			$now = strtotime("now");
 			if ($now > $targetTime)
 				$targetTime = strtotime("+1 day", $targetTime);

 			if ($time != "once") {
	 			syslog(LOG_INFO, "backup for ".$custName." scheduled to: ".date("d-m-Y H:i", $targetTime));
				time_sleep_until($targetTime);
			}

			$filename = $custName."-backup";

			try {
	 			if (!writeBackupToFile($filename))
	 				continue;

	 			$service = getGoogleDriveService();

	 			$parameters = array('q' => "title = '".$filename."' and trashed = false and mimeType = 'text/csv'");
	 			$fileList = $service->files->listFiles($parameters);

	 			if ($files = $fileList->getItems()) {
	 				//echo "File exists\n";
	 				$file = $files[0];
	 				$result = $service->files->update($file->getId(), $file, array(
	 				  	'data' => file_get_contents($filename),
	 				  	'mimeType' => 'text/csv',
	 				  	'uploadType' => 'media'),
	 					array('convert'=>true, 'newRevision' => true)
	 				);
	 			}
	 			else {
	 				//echo "New file\n";
	 				$file = new Google_Service_Drive_DriveFile();
	 				$file->setTitle($filename);
		 			$result = $service->files->insert($file, array(
		 			  	'data' => file_get_contents($filename),
		 			  	'mimeType' => 'text/csv',
		 			  	'uploadType' => 'media'),
		 				array('convert'=>true, 'newRevision' => true)
		 			);

		 			$newPermission= new Google_Service_Drive_Permission();
		 			$newPermission->setType('user');
		 			$newPermission->setRole('writer');
		 			$newPermission->setValue('admin@googmesh.com'); //thats email to share
		 			$result = $service->permissions->insert($result->getId(),$newPermission);
		 		}
		 		syslog(LOG_INFO, "backup completed, file: ".$filename);
		 		echo "backup completed, file: ".$filename;
	 		}
	 		catch(Exception $e) {
	 			syslog (LOG_ERR, "Exception: " .$e->getMessage());
	 			continue;	
	 		}			 		
			if ($time == "once")
				break;	 			
 			sleep(10);	// wait until target time has passed
 		}


function writeBackupToFile($filename) {
	global $mainTable, $fieldTable;

	syslog(LOG_INFO, "writing backup to ". $filename);
	$sql = "SELECT * FROM $mainTable";
    $result = mysql_query($sql);

    $sql = "Select name FROM $fieldTable";
    $res = mysql_query($sql);
    $titles[0] = "id";

    for ($i=0; $field = mysql_fetch_array($res); $i++)
    	$titles[$i+1] = $field["name"];

	$file = fopen($filename, 'w');
	if (!$file) {
		echo "Unable to open output file: ".$filename;
		syslog(LOG_ERR, "Unable to open output file: ".$filename);
		return false; 

	}
	// write column titles 
    fputcsv($file, $titles);

    while ($record = mysql_fetch_assoc($result)) {
    	fputcsv($file, $record);
    }
    return true;

}


function getGoogleDriveService() {
	global $globalDBName, $appName;

	$customer = getClient($globalDBName, 1);	// always use the first client

	$clientid = $customer["clientID"];
	$clientmail = $customer["clientMail"];
	$clientkeypath = $customer["clientKeyPath"];

	try {
		syslog (LOG_INFO, "Get Google drive service");

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
		    array('https://www.googleapis.com/auth/drive'),
		    $key
		);
		$client->setAssertionCredentials($cred);
		
		
		if ($client->getAuth()->isAccessTokenExpired()) {
		  $client->getAuth()->refreshTokenWithAssertion($cred);
		}
			   
		if ($client->getAccessToken()) {
			$driveService = new Google_Service_Drive($client);
		}
		else {
			syslog(LOG_ERR, "could not access drive");
			return null;
		} 
	}
	catch(Exception $e) {
		syslog (LOG_ERR, "Exception: " .$e->getMessage());
		return null;	
	}	
	syslog(LOG_INFO, "Drive service initiated<br>");
	return $driveService;
}

?>
