<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];

	$user = $_GET['user'];
	$unique = $_GET['unique'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	getClientInfo($dbName);

	// get user calendars

	$calendarList = "";
	$sql = "SELECT * FROM $usersTable WHERE email='$user';";
	$result = mysql_query($sql) or die('get user calendars Failed! ' . mysql_error()); 
	$i = 0;
	while ($cal = mysql_fetch_array($result)) {
		$calNumber = $cal["calendarNum"];
		if ($i++ > 0)
			$calendarList = $calendarList.",";
		$calendarList = $calendarList.$calNumber;
	}

	if ($calendarList == "") {
		echo "User ".$user." is not authorized to any calendar !" ;
		return;		
	}
	//var_dump($calendarList);
	$calendars = [];
	// First get all the calendars
	$sql = "SELECT * FROM $calendarsTable WHERE number IN ($calendarList);";
	$result = mysql_query($sql) or die('get calendars Failed! ' . mysql_error()); 
	$numCalendars = mysql_num_rows($result);
	if ($numCalendars > 0)	{
		//  found calendars

		while ($calendar = mysql_fetch_array($result, MYSQL_ASSOC)) {
			if (!$unique || !calendarExists($calendar, $calendars))
				// add a calendar only if it doesn't exists already or we don't care about unique values
				array_push($calendars, $calendar);	

		}
	}
	else {
		echo " Calendars not found in table" ;
		return;
	}			
	echo json_encode($calendars);	
	

	function calendarExists($calendar, $calendars) {

		$name = $calendar["name"];
		foreach ($calendars as $cal) {
			if ($name == $cal["name"])
				return true;
		}

		return false;

	}
?>