<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";

	// get arguments from command line		
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	
	$command = $_GET['cmd'];
	
	$dbName = $_GET['db'];
	//echo $dbName;
	
	syslog(LOG_INFO, "Running importOrders, command: ".$command);		
	$ssName = getClientInfo($dbName);
	if ($ssName)
		setSSName($ssName);
	else {
		echo "spreadsheet not found for DB: ".$dbName;
		return;		
	}		
			
	if (!selectDB($dbName))
		return;	
	
	if ($command == 'events') {
		// just create new events for existing orders
		$sql = "SELECT * FROM $mainTable ;";
		$orders = mysql_query($sql) or die('get orders Failed! ' . mysql_error()); 		

	}	
	else {	// cmd = 'calc' or 'all'
		// import new orders from a spredsheet
		$inputSpreadsheet = $_GET['input'];
		$orders = importOrders($inputSpreadsheet);
	}
	if ($command == 'calc') {
		// init the spreadsheet
		initGoogleAPI($ssName);	
	}
	
	$i=0;
	while (true) {
		if ($command == 'events') {
			$order = mysql_fetch_array($orders, MYSQL_ASSOC);
			if (!$order)
				break;
			$orderID = $order['id'];
		}
		else {	// import
			if (!array_key_exists($i, $orders))
				break;
			$order = $orders[$i];
		}
		$i++;

		// get the fields
		$sql = "SELECT * FROM $fieldTable ;";
		$fields = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
		if (mysql_num_rows($fields) == 0)
			die('get fields Failed! ' . mysql_error());

		$dates = [];
		$newOrder = [];
		$count = 0;
		while ($field = mysql_fetch_array($fields, MYSQL_ASSOC)) {

			list($key, $value) = each($order);
			if ($count++ == 0) {
				// insert the id value first
				$orderID = $value;
				$values = "'$value' ";
				list($key, $value) = each($order);	// skip the id column			
			}
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

		if ($command != 'events') {
			//echo "values: ".$values."<br>\n";
			// insert each order to the main table
			$sql = "INSERT INTO $mainTable VALUES ($values);";
			$res = mysql_query($sql) or die('insert into main Failed! ' . mysql_error()); 
			//$orderID = mysql_insert_id();	
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
				// echo "Calendar: ".$calendarID." Field: ".$key."OrderID: ".$orderID."<br>\n";
				$sql = "SELECT * FROM $eventsTable WHERE calendarID='$calendarID' AND orderID='$orderID';";
				$ev = mysql_query($sql) or die('get event from events table Failed! ' . mysql_error());
				if (mysql_num_rows($ev) == 0) {
					// event record doesn't exist in table - insert it	
					if (strtotime($dates[$key]) && $dates[$key] != "0000-00-00 00:00:00") { // ignore invalid dates			
			   		$sql = "INSERT INTO $eventsTable VALUES ('$calendarID', '$orderID', '$dates[$key]', '', 0 )";
			   		if (!mysql_query($sql)) die('Insert event Failed !' . mysql_error());
			   	}
			   	//else echo "Invalid date: ".$dates[$key]."<br>\n";
				}
				else {  // event record exist -  update it with the new date and set updated to 0
			   		$sql = "UPDATE $eventsTable set eventDate='$dates[$key]', updated='0' WHERE calendarID='$calendarID' AND orderID='$orderID'";
			   		if (!mysql_query($sql)) die('Update event Failed !' . mysql_error());
				}
			}
		}

		echo "Order ID: ".$orderID." processed<br>\n";
	
	}
 
 

?>