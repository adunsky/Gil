<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];
	//echo $dbName;

	$orderID = $_GET["orderID"];
	if (!selectDB($dbName))
		return;	

	$field = [];
	$field["value"] = $_GET["value"];
	$field["index"] = $_GET["index"];

	syslog(LOG_INFO, "check Unique called, field value: ".$field["value"]);

	if (isUnique($field, $orderID))
		echo "true";
	else
		echo "false";

?>