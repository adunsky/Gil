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
	$user = $data["user"];

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

	$newCharge = false;
	$chargeFieldValue = "";
	foreach ($origOrder as $field) {
		$input = $field["input"];
		$type = $field["type"];

		if ($type == "CHARGE") {
			$chargeFieldIndex = $field["index"];
			$existingChargeValue = "";
		}

		if ($input == 'U') {	// Unique value		
			if (!isUnique($field, $orderID)) {
				syslog (LOG_ERR, "Order ".$orderID.": field value ".$field["value"]." already exists !");
				echo $field["value"]." already exists";
				return;
			}
		}
	}

	if ($orderID > 0) {
		// check if order exists
		$sql = "SELECT * FROM $mainTable WHERE id = $orderID;";
		$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0) {
			if ($chargeFieldIndex) {
				$order = mysql_fetch_assoc($result);
				$existingChargeValue = $order[$chargeFieldIndex];
			}

		}
		else {	
			syslog (LOG_ERR, "Order ".$orderID." not found - update failed !");
			echo "Failed to find orderID: ".$orderID;
			return;
		}
	}

	syslog(LOG_INFO, "updateOrder called on ".$ssName." orderID: ".$orderID);

	for ($i=0; initGoogleAPI($ssName) == null && $i<5; $i++)
		syslog(LOG_ERR, "InitGoogleApi Failed"); // retry 5 times to initialize

	$i=0;
	// call spreadsheet to calculate fields
	while (!($order = getCalcFields($origOrder)) && $i++ < 5) {
		// retry call to spreadsheet 
		syslog(LOG_ERR, "getCalcFields Failed. retrying...");
	}
	if (!$order) {
		syslog(LOG_ERR, "getCalcFields Failed after retry");
		echo "Failed to update spreadsheet. Please retry";
		return; 
	}

	//var_dump($order);

	if ($orderID > 0) // existing order
		$values = ""; 
	else // new order
		$values = "'null' "; // null for the auto generated ID
	
	$dates = [];	
	foreach ($order as $field) {
		$name = $field["index"];
		$value = $field["value"];
		$type = $field["type"];
		$input = $field["input"];

		if ($type == 'CHARGE' && $existingChargeValue != $value) {
			$newCharge = true;
			$chargeFieldValue = $value;
		}

		// check again that order is unique, in case of duplicate (error) order values returned from spreadsheet
		if ($input == 'U') {	// Unique value		
			if (!isUnique($field, $orderID)) {
				syslog (LOG_ERR, "Order ".$orderID.": field value, after calculation: ".$field["value"]." already exists !");
				echo "System error - please try again !<br>\n". $field["value"]." already exists";
				return;
			}
		}

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
 		
		if ($orderID > 0) {	
				if ($values != "") // after the first name=value pair
					$values .= ", ";
				$values .= "`$name` = '$value'"; // reverse qoutes needed to support Hebrew text	
		}
		else {	// new order
				$values .= ", '$value'";
		}
	}
	// Update the main table 	
	if ($orderID <= 0) {
	  	syslog (LOG_INFO, "Inserting new order");
	   $sql = "INSERT INTO $mainTable VALUES ($values)";
	   if (!mysql_query($sql)) die('Insert Order Failed !' . mysql_error());
		$orderID = mysql_insert_id();
		if (!mysql_query("INSERT INTO $logTable VALUES (0, CURRENT_TIMESTAMP, '$orderID', '$user', 'created', '$chargeFieldValue')"))
			syslog (LOG_ERR, "Failed to insert to log table, ". mysql_error());	
	}
	else { 
		syslog (LOG_INFO, "updating existing order ".$orderID);
		$sql = "UPDATE $mainTable set $values WHERE id = $orderID;";
	   	if (!mysql_query($sql)) die('Update Order Failed !' . mysql_error());
	   	if (!mysql_query("INSERT INTO $logTable VALUES (0, CURRENT_TIMESTAMP, '$orderID', '$user', 'updated', '$chargeFieldValue')"))
	   		syslog (LOG_ERR, "Failed to insert to log table, ". mysql_error());	
	}
	if ($newCharge) {
		if (!mysql_query("INSERT INTO $logTable VALUES (0, CURRENT_TIMESTAMP, '$orderID', '$user', 'charged', '$chargeFieldValue')"))
			syslog (LOG_ERR, "Failed to insert to log table, ". mysql_error());	

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
				if (strtotime($dates[$key]) && $dates[$key] != "0000-00-00 00:00:00") { // ignore invalid dates	
				syslog (LOG_INFO, "Adding new event for Calendar: ".$calendarID." Field: ".$key." Date: ".$dates[$key]);		
		   		$sql = "INSERT INTO $eventsTable VALUES ('$calendarID', '$orderID', '$dates[$key]', '', 0 )";
		   		if (!mysql_query($sql)) die('Insert event Failed !' . mysql_error());
		   	}
		   	//else echo "Invalid date: ".$dates[$key]."<br>\n";
			}
			else {  // event record exist -  update it with the new date and set updated to 0
				syslog (LOG_INFO, "Updating existing event for Calendar: ".$calendarID." Field: ".$key." Date: ".$dates[$key]);
		   		$sql = "UPDATE $eventsTable set eventDate='$dates[$key]', updated='0' WHERE calendarID='$calendarID' AND orderID='$orderID'";
		   		if (!mysql_query($sql)) die('Update event Failed !' . mysql_error());
			}
		}
	}

	updateEmails($orderID);
	syslog (LOG_INFO, "Order update complete: ".$orderID);
	echo $orderID;
	

function updateEmails($orderID) {
	global $mainTable, $emailCfgTable, $emailsTable;

	$sql = "SELECT * FROM $mainTable WHERE id = $orderID;";
	$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
	$order = mysql_fetch_assoc($result);
	if (!$order) {
		// failed to get the order from main
		syslog(LOG_ERR, "Failed to select order from main: ".$orderID);
		return;
	}
	$sql = "SELECT * FROM $emailCfgTable";
	$result = mysql_query($sql) or die('get emailCfg Failed! ' . mysql_error()); 	

	while($emailCfg = mysql_fetch_assoc($result)) {
		//	found email to queue in emails table
		$num = $emailCfg["num"];
		$emailTo = $order[$emailCfg["emailTo"]];
		$fromName = $order[$emailCfg["fromName"]];
		$fromEmail = $order[$emailCfg["fromEmail"]];
		$subject = $order[$emailCfg["subject"]];
		$content = $order[$emailCfg["content"]];
		$schedule = $order[$emailCfg["schedule"]];

		if ((!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) &&
			$emailTo != "" &&
			$fromName != "" &&
			$subject != "") {

			// valid email fields
			$sql = "SELECT * FROM $emailsTable WHERE orderID = $orderID AND num = $num;";
			$res = mysql_query($sql) or die('get order Failed! ' . mysql_error());
			if (mysql_num_rows($res) > 0) {
				// update existing email
				$sql = "UPDATE $emailsTable SET emailTo='$emailTo', fromName='$fromName', fromEmail='$fromEmail', subject='$subject', content='$content', schedule='$schedule', updated='0' WHERE num='$num' AND orderID='$orderID';";
				if (!mysql_query($sql)) die('Update Email Failed !' . mysql_error());
				syslog(LOG_INFO, "Updated email to: ".$emailTo." at: ".$schedule." for order: ".$orderID);


			}
			else {
				// insert new email to send
				$sql = "INSERT INTO $emailsTable VALUES ('$num', '$orderID', '$emailTo', '$fromName', '$fromEmail', '$subject', '$content', '$schedule', 0)";
				if (!mysql_query($sql)) die('Insert Email Failed !' . mysql_error());
				syslog(LOG_INFO, "Inserted Email to: ".$emailTo." at: ".$schedule." for order: ".$orderID);
			}
		}
		else
			syslog(LOG_ERR, "Invalid email fields: ".$emailTo." , ".$fromName." , ".$fromEmail." , ".$subject." for order: ".$orderID);	
	}

}	
 
?>