<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	getClientInfo($dbName);


	$fields = [];
	// First get all the fields
	$sql = "SELECT * FROM $fieldTable;";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	$numFields = mysql_num_rows($result);
	if ($numFields > 0)	{
		//  found fields

		while ($field = mysql_fetch_array($result, MYSQL_ASSOC)) {

			array_push($fields, $field);	

		}
	}
	else {
		echo " Columns not found in table" ;
		return;
	}			
	echo json_encode($fields);	
	

?>