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
	$order = $data["order"];
	$orderID = $data["orderID"]; 
	
	$order = getCalcFields($order);  // get from spreadsheet

	if ($orderID) {
		// check if order exists
		$sql = "SELECT * FROM $mainTable WHERE id = $orderID;";
		$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) == 0)	
			//  order not found - insert as new
			$orderID = null;
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
		if ($type == "DATE") {
			// need to format the date returned from the spreadsheet
			$date = str_replace('/', '-', $value);
			if ($date = strtotime($date)) {
				$value = date('Y-m-d', $date);
				// echo "date: ".$value."\n";
			}
			else {
				$value = "0000-00-00";				
			}
			$dates[$name] = $value;
	
		}
 		
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
	  	// insert as new order
	   $sql = "INSERT INTO $mainTable VALUES ($values)";
	   if (!mysql_query($sql)) die('Insert Order Failed !' . mysql_error());
		$orderID = mysql_insert_id();
	}
	else { 
		// update existing order
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
			// echo "Calendar: ".$calendarID." Field: ".$key."OrderID: ".$orderID."<br>\n";
			$sql = "SELECT * FROM $eventsTable WHERE calendarID='$calendarID' AND fieldIndex='$key' AND orderID='$orderID';";
			$ev = mysql_query($sql) or die('get event from events table Failed! ' . mysql_error());
			if (mysql_num_rows($ev) == 0) {
				// event record doesn't exist in table - insert it	
				if (strtotime($dates[$key]) && ($dates[$key] != "0000-00-00")) { // ignore invalid dates			
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
	
	echo $orderID;
	
	
 
?>