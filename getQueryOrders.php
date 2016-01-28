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
	$filters = $_GET['filters'];
	$filterList = json_decode($filters);
	$user = $_GET["user"];	

	if (!selectDB($dbName))
		return;	

	$calendarList = explode(",", $calendars);
	
	for ($i = 0; array_key_exists($i, $calendarList); $i++) {
		//var_dump($calendarList);
		if (!authUserCalendar($user, trim($calendarList[$i], "'"))) {
			echo "The user is not authorized to perform this action.";
			return;	
		}
	}

	//var_dump($calendarList);

	$startDate = date('Y-m-d H:i:s', strtotime($start));
	$end = strtotime($end);
	$endDate = date('Y-m-d H:i:s', strtotime("-1 minute", $end));	// until 23:59 of the last day

	$filterString = getFilterString($filterList);

	syslog(LOG_INFO, "getOrders called, startDate: ".$startDate." endDate: ".$endDate." filter: ".$filterString);

	//echo ("start: ".$startDate." end: ".$endDate);

	$eventList = [];
	// Get the relevant events
	$sql = "SELECT * FROM $eventsTable WHERE eventDate BETWEEN '$startDate' AND '$endDate' ORDER BY eventDate ASC";
	$result = mysql_query($sql) or die('get events Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found events
		while ($event = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$orderID = $event["orderID"];
			if (!orderExists($orderID,$eventList)) {
				$calendarID = $event["calendarID"];
				$eventTime = date('H:i', strtotime($event["eventDate"]));
				if ($eventTime == "00:00")
					$format = 'd/m/Y';
				else
					$format = 'd/m/Y h:i A';					
				$event["eventDate"] = date($format, strtotime($event["eventDate"]));
				$cal = mysql_query("SELECT * FROM $calendarsTable WHERE name IN ($calendars) AND number='$calendarID'") or die('get calendar Failed! ' . mysql_error()); 
				if ($calendar = mysql_fetch_array($cal, MYSQL_ASSOC)) {
					// get the title and location from the first calendar for this event
					$titleFieldIndex = $calendar["titleField"];
					$locationFieldIndex = $calendar["locationField"];
					$main = mysql_query("SELECT * FROM $mainTable WHERE id='$orderID'".$filterString) or die('get order from main Failed! ' . mysql_error()); 
					if ($order = mysql_fetch_array($main, MYSQL_ASSOC)) {
						$order["calendarID"] = $calendarID;
						array_push($eventList, $order);
					}
				}
				//else
				//	syslog(LOG_INFO, "Event for order: ".$orderID." not found in calendar: ".$calendarID);
			}
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

function getFilterString($filterList) {
	global $fieldTable;

	if (!$filterList)
		return "";

	$filterString = "";

	foreach ($filterList as $filter) {
		//var_dump($filter);

		if (isset($filter->name) && $filter->name != "") {
			$name = $filter->name;
			if ($filter->value)
				$value = $filter->value;
			else
				$value = "";
			$result = mysql_query("SELECT * FROM $fieldTable WHERE name='$name'") or die('get order from main Failed! ' . mysql_error()); 
			if ($field = mysql_fetch_array($result, MYSQL_ASSOC)) {	
				$index = $field["index"];
				$filterString = $filterString." AND `$index` LIKE '%$value%'";
			}
		}
	}

	return $filterString;

}


?>