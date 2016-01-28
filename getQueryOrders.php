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
	set_time_limit (0); // no time limit
	//var_dump($calendarList);

	$startDate = date('Y-m-d H:i:s', strtotime($start));
	$end = strtotime($end);
	$endDate = date('Y-m-d H:i:s', strtotime("-1 minute", $end));	// until 23:59 of the last day

	$filterString = getFilterString($filterList);

	syslog(LOG_INFO, "getQueryOrders called, startDate: ".$startDate." endDate: ".$endDate." filter: ".$filterString);

	//echo ("start: ".$startDate." end: ".$endDate);
	// get calendar numbers

	$calendarNums = "";
	$sql = "SELECT * FROM $calendarsTable WHERE name in ($calendars);";
	$result = mysql_query($sql) or die('get calendars Failed! ' . mysql_error()); 
	$i = 0;
	while ($cal = mysql_fetch_array($result)) {
		$calNumber = $cal["number"];
		if ($i++ > 0)
			$calendarNums = $calendarNums.",";
		$calendarNums = $calendarNums.$calNumber;
	}

	$orders = [];
	$orderList = "";
	// Get the relevant events
	$sql = "SELECT * FROM $eventsTable WHERE calendarID IN ($calendarNums) AND eventDate BETWEEN '$startDate' AND '$endDate' ORDER BY eventDate ASC";
	$result = mysql_query($sql) or die('get events Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found events
		$i=0;
		while ($event = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$orderID = $event["orderID"];
			if ($i++ > 0)
				$orderList = $orderList.",";
			$orderList = $orderList.$orderID;
		}

		$main = mysql_query("SELECT * FROM $mainTable WHERE id IN ($orderList)".$filterString) or die('get order from main Failed! ' . mysql_error()); 
		while ($order = mysql_fetch_array($main, MYSQL_ASSOC)) {
			$order["calendarID"] = $calNumber;	// one of the calendars in the list
			array_push($orders, $order);
		}
	}
	else
		echo("Events not found");	

	echo json_encode($orders);


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