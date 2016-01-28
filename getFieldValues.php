<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];

	$fieldName = $_GET['field'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	getClientInfo($dbName);

	$values = [];
	// First get all the fields
	$sql = "SELECT * FROM $fieldTable WHERE name='$fieldName';";
	$result = mysql_query($sql) or die('get field Failed! ' . mysql_error()); 
	if ($field = mysql_fetch_assoc($result)) {

		$fieldIndex = $field["index"];

		$sql = "SELECT * FROM $listValueTable WHERE `index`='$fieldIndex';";
		$result = mysql_query($sql) or die('get field values Failed! ' . mysql_error()); 

		while ($field = mysql_fetch_array($result, MYSQL_ASSOC)) {

			array_push($values, $field["value"]);	

		}
		echo json_encode($values);	
	}
		
	

?>