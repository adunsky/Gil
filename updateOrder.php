<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";
	
	//include_once "calendar.php";
	
   $postdata = file_get_contents("php://input");
   //echo "postdata: " . $postdata;

   $data = json_decode($postdata, true);
  	$dbName = $data["dbName"]; 
	$origOrder = $data["order"];
	$orderID = $data["orderID"]; 
	if (!selectDB($dbName)) {
		syslog(LOG_ERR, "Failed to select client DB: "+$dbName);
		return;	
	}
	$ssName = getClientInfo($dbName);
	if ($ssName)
		setSSName($ssName);
	else {
		syslog(LOG_ERR, "Failed to get client spreadsheet");	
		return;
	}	
	for ($i=0; initGoogleAPI($ssName) == null && $i<5; $i++)
		syslog(LOG_ERR, "InitGoogleApi Failed"); // retry 5 times to initialize

	$order = getCalcFields($origOrder);  // get from spreadsheet
	if (!$order)
		$order = getCalcFields($origOrder);  // retry once

	if (!$order) {
		echo "Spreadsheet returned unknown error, please retry";
		return; 
	}

	//var_dump($order);
	if ($orderID) {
		// check if order exists
		$sql = "SELECT * FROM $mainTable WHERE id = $orderID;";
		$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) == 0) {	
			syslog (LOG_INFO, "Order ".$orderID." not found - insert as new");
			$orderID = null;
		}
	}

	if ($orderID) // existing order
		$values = ""; 
	else // new order
		$values = "'null' "; // null for the auto generated ID
	
	$dates = [];	
	foreach ($order as $field) {
		$name = $field["index"];
		$value = $field["value"];
		$type = $field["type"];
		if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
			$type = "DATETIME";  // it behaves like DATETIME
		if ($type == "DATE" || $type == "DATETIME") {
			// need to add to the dates array to add to the events table
			if ($date = strtotime($value)) {
				//echo "Date ".$value." found in field ".$name."<br>\n";	
				// format it to DB date format
				$date = date('Y-m-d H:i:s', $date);
				$dates[$name] = $date;
				$value = $date;	// save it in YYYY-MM-DD format
				//echo "Date after formating: ".$date."<br>\n";					
			}
			else	// not a valid date
				$dates[$name] = "0000-00-00 00:00:00";
				
			//syslog (LOG_DEBUG, "Date ".$date." found in field ".$name);
		}
		$value = mysql_real_escape_string($value);	// handle special characters
 		
		if ($orderID) {	
				if ($values != "") // after the first name=value pair
					$values .= ", ";
				$values .= "`$name` = '$value'"; // reverse qoutes needed to support Hebrew text	
		}
		else {	// new order
				$values .= ", '$value'";
		}
	}
	// Update the main table 	
	if (!$orderID) {
	  	syslog (LOG_INFO, "Inserting new order");
	   $sql = "INSERT INTO $mainTable VALUES ($values)";
	   if (!mysql_query($sql)) die('Insert Order Failed !' . mysql_error());
		$orderID = mysql_insert_id();
	}
	else { 
		syslog (LOG_INFO, "updating existing order ".$orderID);
		$sql = "UPDATE $mainTable set $values WHERE id = $orderID;";
	   if (!mysql_query($sql)) die('Update Order Failed !' . mysql_error());		
	}

	// Update the events table with the updated dates
	$keys = array_keys($dates);
	foreach ($keys as $key) { // loop on date fields
		// get the calendar ID
		$sql = "SELECT * FROM $calendarsTable WHERE fieldIndex = $key;";
		$result = mysql_query($sql) or die('get field from calendar Failed! ' . mysql_error()); 

		while ($calendar = mysql_fetch_array($result)) {
			// loop on this date field for each calendar it appears in
			$calendarID = $calendar["number"];

			$sql = "SELECT * FROM $eventsTable WHERE calendarID='$calendarID' AND orderID='$orderID';";
			$ev = mysql_query($sql) or die('get event from events table Failed! ' . mysql_error());
			if (mysql_num_rows($ev) == 0) {
				// event record doesn't exist in table - insert it	
				syslog (LOG_INFO, "Adding new event for Calendar: ".$calendarID." Field: ".$key."Date: ".$dates[$key]);
				if (strtotime($dates[$key]) && $dates[$key] != "0000-00-00 00:00:00") { // ignore invalid dates			
		   		$sql = "INSERT INTO $eventsTable VALUES ('$calendarID', '$orderID', '$dates[$key]', '', 0 )";
		   		if (!mysql_query($sql)) die('Insert event Failed !' . mysql_error());
		   	}
		   	//else echo "Invalid date: ".$dates[$key]."<br>\n";
			}
			else {  // event record exist -  update it with the new date and set updated to 0
				syslog (LOG_INFO, "Updating existing event for Calendar: ".$calendarID." Field: ".$key."Date: ".$dates[$key]);
		   		$sql = "UPDATE $eventsTable set eventDate='$dates[$key]', updated='0' WHERE calendarID='$calendarID' AND orderID='$orderID'";
		   		if (!mysql_query($sql)) die('Update event Failed !' . mysql_error());
			}
		}
	}
	
	echo $orderID;
	
	
 
?>