<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	$user = $_GET['user'];
	getClientInfo($dbName);

	$role = getUserRole($user);
			
	echo $role;	
	

?>