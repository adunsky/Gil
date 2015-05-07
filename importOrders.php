<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";

	// get arguments from command line		
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	
	$inputSpreadsheet = $_GET['input'];
	$command = $_GET['cmd'];
	
	$dbName = $_GET['db'];
	//echo $dbName;
			
	$ssName = getClientSS($dbName);
	if ($ssName)
		setSSName($ssName);
	else {
		echo "spreadsheet not found for DB: ".$dbName;
		return;		
	}		
			
	if (!selectDB($dbName))
		return;	
			
	$orders = importOrders($inputSpreadsheet);
	initGoogleAPI($ssName);	
	
	for ($i=0; array_key_exists($i, $orders); $i++) {
		$order = $orders[$i];
	//foreach ($orders as $order) {

		// get the fields
		$sql = "SELECT * FROM $fieldTable ;";
		$fields = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
		if (mysql_num_rows($fields) == 0)
			die('get fields Failed! ' . mysql_error());

		$values = "'null' ";
		$dates = [];
		$newOrder = [];
		while ($field = mysql_fetch_array($fields, MYSQL_ASSOC)) {

			list($key, $value) = each($order);
			$field["value"] = $value;

			$name = $field["index"];
			//echo "Index: ".$name." value: ".$value."<br>\n";
			if ($value == '_none')
				$value = "";
			$type = $field["type"];

			if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
				$type = "DATETIME";  // it behaves like DATETIME
			if ($type == "DATE" || $type == "DATETIME") {
				// need to add to the dates array to add to the events table
				$value = str_replace('/', '-', $value);
				if ($date = strtotime($value)) {
					// format it to DB date format
					if ($type == "DATE")
						$date = date('Y-m-d', $date);
					else // DATETIME
						$date = date('Y-m-d H:i', $date);							
					$dates[$name] = $date;
				}
				else	// not a valid date
					$dates[$name] = "0000-00-00 00:00:00";
			}
		
			$value = mysql_real_escape_string($value);	// handle special characters
			$values .= ", '$value'";

			array_push($newOrder, $field);
		}
		
		if ($command == 'calc') {
			// send to spreadsheet for calculation
			$order = getCalcFields($newOrder);  // get from spreadsheet
			if ($order == null){
				echo "getCalcFields failed\n";
				sleep(5);
				initGoogleAPI($ssName);
				$i--; // go back to prev order
				continue;			
			}
				
			$values = "'null' ";
			$dates = [];
			foreach ($order as $field) {
				$name = $field["index"];
				$value = $field["value"];
				//echo "Index: ".$name." value: ".$value."<br>\n";
				if ($value == '_none')
					$value = "";
				$type = $field["type"];
	
				if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0)
					$type = "DATETIME";  // it behaves like DATETIME
				if ($type == "DATE" || $type == "DATETIME") {
					// need to add to the dates array to add to the events table
					$value = str_replace('/', '-', $value);
					if ($date = strtotime($value)) {
						// format it to DB date format
						if ($type == "DATE")
							$date = date('Y-m-d', $date);
						else // DATETIME
							$date = date('Y-m-d H:i', $date);							
						$dates[$name] = $date;
					}
					else	// not a valid date
						$dates[$name] = "0000-00-00 00:00:00";
				}
			
				$value = mysql_real_escape_string($value);	// handle special characters
				$values .= ", '$value'";
				
			}

		}

		//echo "values: ".$values."<br>\n";
		// insert each order to the main table
		$sql = "INSERT INTO $mainTable VALUES ($values);";
		$res = mysql_query($sql) or die('insert into main Failed! ' . mysql_error()); 
		$orderID = mysql_insert_id();	

		// Update the events table with the updated dates
		$keys = array_keys($dates);
		foreach ($keys as $key) { // loop on date fields
			// get the calendar ID
			$sql = "SELECT * FROM $calendarsTable WHERE fieldIndex = $key;";
			$result = mysql_query($sql) or die('get field from calendar Failed! ' . mysql_error()); 
	
			while ($calendar = mysql_fetch_array($result)) {
				// loop on this date field for each calendar it appears in
				$calendarID = $calendar["number"];
				// echo "Calendar: ".$calendarID." Field: ".$key."OrderID: ".$orderID."<br>\n";
				$sql = "SELECT * FROM $eventsTable WHERE calendarID='$calendarID' AND fieldIndex='$key' AND orderID='$orderID';";
				$ev = mysql_query($sql) or die('get event from events table Failed! ' . mysql_error());
				if (mysql_num_rows($ev) == 0) {
					// event record doesn't exist in table - insert it	
					if (strtotime($dates[$key]) && $dates[$key] != "0000-00-00 00:00:00") { // ignore invalid dates			
			   		$sql = "INSERT INTO $eventsTable VALUES ('$calendarID', '$key', '$orderID', '$dates[$key]', '', 0 )";
			   		if (!mysql_query($sql)) die('Insert event Failed !' . mysql_error());
			   	}
			   	//else echo "Invalid date: ".$dates[$key]."<br>\n";
				}
				else {  // event record exist -  update it with the new date and set updated to 0
			   		$sql = "UPDATE $eventsTable set eventDate='$dates[$key]', updated='0' WHERE calendarID='$calendarID' AND fieldIndex='$key' AND orderID='$orderID'";
			   		if (!mysql_query($sql)) die('Update event Failed !' . mysql_error());
				}
			}
		}

		echo "Order ID: ".$orderID." processed<br>\n";
	
	}
 
 

?>