<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	   //var_dump($_GET);
	$dbName = $_GET['db'];
	//echo $dbName;

	if (!selectDB($dbName))
		return;	

	syslog(LOG_INFO, "getSearchFields called, DB: ".$dbName);

	$fieldList = [];
	// Get the relevant events
	$sql = "SELECT * FROM $fieldTable WHERE searchable='Y'";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	while ($field = mysql_fetch_array($result, MYSQL_ASSOC)) {
			array_push($fieldList, $field);
	}

	echo json_encode($fieldList);


?>