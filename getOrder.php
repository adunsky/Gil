<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	 
   //var_dump($_GET);
	$dbName = $_GET['db'];
	//echo $dbName;
	
	$row = [];
	$orderID = 0;
	$formID = 1; // This is the default form

	if (!selectDB($dbName))
		return;	
			
	$eventID = $_GET["eventID"];
	if ($eventID == "0") { // it is a new order - verify the user email
		//echo $eventID;
		$user = $_GET["user"];	
		if ($eventID == 0 && !authUserForm($user, $formID)) {
			echo "The user is not authorized to perform this action.";
			return;	
		}
	}
	
	
	// First get all the fields
	$sql = "SELECT * FROM $fieldTable;";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	$numFields = mysql_num_rows($result);
	if ($numFields > 0)	{
		//  found fields

		while ($fields = mysql_fetch_array($result, MYSQL_ASSOC)) {

			array_push($row, $fields);	

		}
	}
	else {
		echo " Columns not found in table" ;
		return;
	}

	if ($eventID != '0') {
		$sql = "SELECT * FROM $eventsTable WHERE eventID = '$eventID';";
		$result = mysql_query($sql) or die('get eventID Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found event
			$event = mysql_fetch_array($result, MYSQL_ASSOC);
			$orderID = $event["orderID"];	
			$calendarID = $event["calendarID"];
			//echo $orderID;
	
			$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarID';";
			$result = mysql_query($sql) or die('get form from calendar Failed! ' . mysql_error()); 
			if (mysql_num_rows($result) > 0)	{
				//  found calendar
				$calendar=mysql_fetch_array($result, MYSQL_ASSOC);
				$formID = $calendar["formNumber"];
			}
		
			$sql = "SELECT * FROM $mainTable WHERE id = '$orderID';";
			$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
			if (mysql_num_rows($result) > 0)	{
				//  found order - set the values from the main table
				$order = mysql_fetch_array($result, MYSQL_ASSOC);
				for($i=0; $i < $numFields ; $i++) {
					$index = $row[$i]["index"];
					$row[$i]["value"] = $order[$index];	
		
					if ($row[$i]["value"] == "" && ($row[$i]["type"] == "Hyperlink" || $row[$i]["type"] == "EmbedHyperlink")) {
						// For Hyperlinks set the default value in case no value is set
						$row[$i]["value"] = $row[$i]["default"];			
					}
					$type = $row[$i]["type"];
						
	
					if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0) {
						$type = "DATETIME";  // it behaves like DATETIME
						$row[$i]["type"] = $type;
					}
					if ($type == "DATE" || $type == "DATETIME")	 {
						// format the datetime for the UI
						if ($date = strtotime($row[$i]["value"])) {
							if ($type == "DATE")
								$row[$i]["value"] = date('d-m-Y', $date);
							else // DATETIME	
								$row[$i]["value"] = date('Y-m-d H:i', $date);
						}	
					}			
				}
				
			}
			else
				$orderID = -1;
		}
		else 
			$orderID = -1;
	}
	if ($orderID == -1) {
		echo "Invalid record ID";
		return;
	}	
		
	if ($orderID == 0) {// It is a new order
		for($i=0; $i < $numFields ; $i++) {
			$type = $row[$i]["type"];
			if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0) {
				$type = "DATETIME";  // it behaves like DATETIME
				$row[$i]["type"] = $type;
			}
			if (($type == "DATE" || $type == "DATETIME") && $row[$i]["default"] != "") {
				if ($date = strtotime($row[$i]["default"])) {
					if ($type == "DATE")
						$row[$i]["value"] = date('d-m-Y', $date);
					else // DATETIME	
						$row[$i]["value"] = date('Y-m-d H:i', $date);	
				}
			}
			else
				$row[$i]["value"] = $row[$i]["default"];  // return default in all values		
		}
	
	}
	
	$data = [];
	$data["orderID"] = $orderID;
	$data["formID"] = --$formID;	// subtract 1 since it is an array index
	$data["order"] = $row;
	echo json_encode($data);

?>