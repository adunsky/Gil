<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	   //var_dump($_GET);
	$dbName = $_GET['db'];
	//echo $dbName;
	$start = $_GET['startDate'];
	$end = $_GET['endDate'];
	$calendars = $_GET['calendars'];
	//echo $calendars;

	if (!selectDB($dbName))
		return;	
	
/*
	$user = $_GET["user"];	
	if (!authUserForm($user, 0)) {
			echo "The user is not authorized to perform this action.";
			return;	
	}
*/	

	$startDate = date('Y-m-d', strtotime($start));
	$endDate = date('Y-m-d', strtotime($end));

	syslog(LOG_INFO, "getOrders called, startDate: ".$startDate." endDate: ".$endDate);

	//echo ("start: ".$startDate." end: ".$endDate);

	$eventList = [];
	// Get the relevant events
	$sql = "SELECT * FROM $eventsTable WHERE eventDate BETWEEN '$startDate' AND '$endDate';";
	$result = mysql_query($sql) or die('get events Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found events
		while ($event = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$orderID = $event["orderID"];
			//if (!orderExists($orderID,$eventList)) {
				$calendarID = $event["calendarID"];
				$event["eventDate"] = date('d/m/Y h:i A', strtotime($event["eventDate"]));
				$cal = mysql_query("SELECT * FROM $calendarsTable WHERE name IN ($calendars) AND number='$calendarID'") or die('get calendar Failed! ' . mysql_error()); 
				if ($calendar = mysql_fetch_array($cal, MYSQL_ASSOC)) {
					// get the title and location from the first calendar for this event
					$titleFieldIndex = $calendar["titleField"];
					$locationFieldIndex = $calendar["locationField"];
					$main = mysql_query("SELECT * FROM $mainTable WHERE id='$orderID'") or die('get order from main Failed! ' . mysql_error()); 
					if ($order = mysql_fetch_array($main, MYSQL_ASSOC)) {	
						$event["title"] = $order[$titleFieldIndex];
						$event["location"] = $order[$locationFieldIndex];
					}
					syslog(LOG_INFO, "Event added to list: ".$event["title"]);
					array_push($eventList, $event);
				}
				else
					syslog(LOG_INFO, "Event for order: ".$orderID." not found in calendar: ".$calendarID);
			//}
		}
	}
	else
		echo("Events not found");	

	echo json_encode($eventList);



function orderExists($orderID, $eventList) {
	for ($i=0; array_key_exists($i, $eventList) ; $i++) {
		if (in_array($orderID, $eventList[$i]))
			return true;

	}
	return false;

}

?>