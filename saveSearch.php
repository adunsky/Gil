<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

   	$postdata = file_get_contents("php://input");
   	//echo "postdata: " . $postdata;

   	$data = json_decode($postdata, true);
  	$dbName = $data["dbName"]; 
	$user = $data["user"];
	$filterList = $data["filterList"];
	$displayFields = $data["displayFields"];
	$name = $data["name"];
	$calendar = $data["calendar"];
	$calName = $calendar["name"];
	$startDate = $calendar["startDate"];
	$endDate = $calendar["endDate"];


	if (!selectDB($dbName)) {
		syslog(LOG_ERR, "Failed to select client DB: "+$dbName);
		return;	
	}

	$sql = "SELECT * FROM $searchTable WHERE user='$user' AND name='$name'";
	$result = mysql_query($sql) or die('Select search table Failed! ' . mysql_error()); 

	$searchID = 0;
	if ($search = mysql_fetch_array($result)) {
		$searchID = $search["id"];
		$sql = "UPDATE $searchTable SET calendar='$calName', startDate='$startDate', endDate='$endDate' WHERE user='$user' AND name='$name'";
		$result = mysql_query($sql) or die('Update search table Failed! ' . mysql_error()); 
	}
	else {	// new search
		$sql = "INSERT into $searchTable VALUES (null, '$user', '$name', '$calName', '$startDate', '$endDate') ";
		$result = mysql_query($sql) or die('Insert into search table Failed! ' . mysql_error()); 
		$searchID = mysql_insert_id();
	}

	$sql = "DELETE FROM $filterTable WHERE searchID='$searchID'";
	$result = mysql_query($sql) or die('Delete from filter table Failed! ' . mysql_error()); 

	foreach($filterList as $filter) {
		if (array_key_exists("name", $filter))
			$filterName = $filter["name"];
		else
			continue;	// Filter must have a name
		if (array_key_exists("value", $filter))
			$filterValue = $filter["value"];
		else
			$filterValue = "";
		$sql = "INSERT into $filterTable VALUES ('$searchID', '$filterName', '$filterValue')";
		$result = mysql_query($sql) or die('Insert into filter table Failed! ' . mysql_error()); 
	}

	$sql = "DELETE FROM $displayFieldsTable WHERE searchID='$searchID'";
	$result = mysql_query($sql) or die('Delete from display fields table Failed! ' . mysql_error()); 

	foreach($displayFields as $field) {

		$sql = "INSERT into $displayFieldsTable VALUES ('$searchID', '$field')";
		$result = mysql_query($sql) or die('Insert into display field table Failed! ' . mysql_error()); 
	}

	syslog (LOG_INFO, "Search save complete: ".$search["name"]);

 	echo $searchID;

?>