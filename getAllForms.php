<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 

	$forms = [];
	$sql = "SELECT * FROM $formsTable ";
	$result = mysql_query($sql) or die('get forms Failed! ' . mysql_error()); 
	while ($form = mysql_fetch_array($result))	{
		array_push($forms, $form);
	}
			
	echo json_encode($forms);	
	

?>