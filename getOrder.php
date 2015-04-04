<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
		 
   //var_dump($_GET);
	
	$eventID = $_GET["eventID"];
	$row = [];
	$orderID = 0;
	$formID = 1; // This is the default form
	
	// First get all the fields
	$sql = "SELECT * FROM $fieldTable;";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	$numFields = mysql_num_rows($result);
	if ($numFields > 0)	{
		//  found fields

		while ($column = mysql_fetch_array($result)) {
			array_push($row, $column);	
		}
	}
	else {
		echo " Columns not found in table" ;
		return;
	}

	$sql = "SELECT * FROM $eventsTable WHERE eventID = '$eventID';";
	$result = mysql_query($sql) or die('get eventID Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found event
		$event = mysql_fetch_array($result);
		$orderID = $event["orderID"];	
		$calendarID = $event["calendarID"];
		//echo $orderID;

		$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarID';";
		$result = mysql_query($sql) or die('get form from calendar Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found calendar
			$calendar=mysql_fetch_array($result);
			$formID = $calendar["formNumber"];
		}
	
		$sql = "SELECT * FROM $mainTable WHERE id = '$orderID';";
		$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found order
			$order = mysql_fetch_array($result, MYSQL_ASSOC);
			for($i=0; $i < $numFields ; $i++) {
				$index = $row[$i]["index"];
				$row[$i]["value"] = $order[$index];	
			}
		}
		
	}
	else {// event not found - new order

		for($i=0; $i < $numFields ; $i++) {
			$row[$i]["value"] = "";  // return space in all values		
		}
	
	}
	
	$data = [];
	$data["orderID"] = $orderID;
	$data["formID"] = --$formID;	// subtract 1 since it is an array index
	$data["order"] = $row;
	echo json_encode($data);

?>