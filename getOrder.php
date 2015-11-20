<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	   //var_dump($_GET);
	$dbName = $_GET['db'];
	//echo $dbName;
	if (array_key_exists("orderID", $_GET))
		$orderID = $_GET["orderID"];
	else
		$orderID = 0;
	if (array_key_exists("calendarNum", $_GET))	
		$calendarID = $_GET["calendarNum"];
	else
		$calendarID = 0;

	if (!selectDB($dbName))
		return;	
	
	$eventID = $_GET["eventID"];
	$user = $_GET["user"];	

	syslog(LOG_INFO, "getOrder called, eventID: ".$eventID." orderID: ".$orderID." user: ".$user);	
	if ($eventID == '0' && $orderID == 0) { 
		//echo $eventID;
		$newOrder = true;	// it is a new order
	}
	else
		$newOrder = false;


	$row = [];
	$order = null;
	$formID = 1;	// default form
	$participantField = 0;

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
		echo "Columns not found in table" ;
		return;
	}

	if ($eventID != '0') {	// find the orderID and calendarID based on the eventID
		$sql = "SELECT * FROM $eventsTable WHERE eventID = '$eventID';";
		$result = mysql_query($sql) or die('get eventID Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found event
			$event = mysql_fetch_array($result, MYSQL_ASSOC);
			$orderID = $event["orderID"];	
			$calendarID = $event["calendarID"];
			//echo $orderID;
			syslog(LOG_INFO, "Found order ID: ".$orderID);
		}
	}

	// get form and calendar info
	if ($calendarID != 0) {
		$sql = "SELECT * FROM $calendarsTable WHERE number = '$calendarID';";
		$result = mysql_query($sql) or die('get form from calendar Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found calendar
			$calendar=mysql_fetch_array($result, MYSQL_ASSOC);
			$formID = $calendar["formNumber"];
			$participantField = $calendar["participants"];
		}
	}

	// now get the order data
	if ($orderID != 0) {
		$sql = "SELECT * FROM $mainTable WHERE id = '$orderID';";
		$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($result) > 0)	{
			//  found order - set the values from the main table
			$order = mysql_fetch_array($result, MYSQL_ASSOC);
			for($i=0; $i < $numFields ; $i++) {
				$index = $row[$i]["index"];
				$row[$i]["value"] = $order[$index];
	
				if (($row[$i]["value"] == null) ||	// set to default because null is initial db value
					($row[$i]["value"] == "" && ($row[$i]["type"] == "Hyperlink" || $row[$i]["type"] == "EmbedHyperlink"))) {
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
			$orderID = -1;	// order not found in DB
	}


	// check user authorizarion
	if (!authUserForm($user, $formID)) {
		$sql = "SELECT * FROM $formsTable WHERE number = '$formID';";
		$result = mysql_query($sql) or die('get form table Failed! ' . mysql_error()); 
		if ($form = mysql_fetch_array($result))	{
			$formName = $form["title"];
		}
		else
			$formName = "";

		if (!$order || !authParticipant($order, $participantField, $user)) {

			echo "User: ".$user." is not authorized to form: ".$formName;
			return;
		}
	}


	if (!$newOrder && $orderID <= 0) {
		// failed to find an existing order in the DB
		echo "Invalid record ID";
		syslog(LOG_ERR, "Order not found");
		return;
	}	
		
	if ($newOrder) {// It is a new order - initialize the first form
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


function authParticipant($order, $participantField, $user) {
	if ($participantField)
		$participantList = explode(",", $order[$participantField]);	
	else
		$participantList = [];

	foreach ($participantList as $participant) {
		$parEmail = filter_var($participant, FILTER_SANITIZE_EMAIL);
		if ($user == $parEmail) {
			syslog(LOG_INFO, "User: ".$user." authorized as a participant");
			return TRUE;	// user is a participant in this order
		}
	}
	
	syslog(LOG_ERR, "User: ".$user." was NOT authorized as a participant");
	return FALSE;
}


?>