<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";
	

	$sql = "SELECT * FROM $fieldTable ;";
	$fields = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	if (mysql_num_rows($fields, MYSQL_ASSOC) == 0)
		die('get fields Failed! ' . mysql_error());

	$sql = "SELECT * FROM $mainTable ;";
	$records = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
   if ($order = mysql_fetch_array($records, MYSQL_ASSOC)) {
		$orderID = $order["id"]; 
		//$order = getCalcFields($order);  // get from spreadsheet
		
		$dates = [];	
		foreach ($fields as $field) {
			$name = $field["index"];
			$value = $order[$name];
			$type = $field["type"];
			if ($type == "DATE") {
				// need to add to the dates array to add to the events table
				$value = str_replace('/', '-', $value);
				if ($date = strtotime($value)) {
					// format it to DB date format
					$date = date('Y-m-d', $date);
					$dates[$name] = $date;
				}
				else	// not a valid date
					$dates[$name] = "0000-00-00";
	
			}
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
				$sql = "SELECT * FROM $eventsTable WHERE calendarID='$calendarID' AND fieldIndex='$key' AND orderID='$orderID';";
				$ev = mysql_query($sql) or die('get event from events table Failed! ' . mysql_error());
				if (mysql_num_rows($ev) == 0) {
					// event record doesn't exist in table - insert it	
					if (strtotime($dates[$key]) && $dates[$key] != "0000-00-00") { // ignore invalid dates			
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