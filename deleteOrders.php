<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	// get arguments from command line		
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
	
	$command = $_GET['cmd'];
	
	$dbName = $_GET['db'];

	$orderID = $_GET['orderID'];
	//echo $dbName;
			
	if (!selectDB($dbName))
		return;	
	
	if ($command == 'one') {
		// delete a single order
		$sql = "SELECT * FROM $mainTable WHERE `id`='$orderID' ;";
		$orders = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
		if (mysql_num_rows($orders) == 0) {
			echo "Order not found: ".$orderID;
			return;	
		}
	}
	elseif ($command == 'all') {
		// delete all orders !
		$sql = "SELECT * FROM $mainTable ;";
		$orders = mysql_query($sql) or die('get orders Failed! ' . mysql_error()); 
		
	}		

	while ($order = mysql_fetch_array($orders, MYSQL_ASSOC)) {
		$orderID = $order['id'];	// in case of 'all' 
		// Update the events table with "0000-00-00 00:00:00" for the removed orders
		$sql = "UPDATE $eventsTable set eventDate='0000-00-00 00:00:00', updated='0' WHERE orderID='$orderID'";
		if (!mysql_query($sql)) die('Update event Failed !' . mysql_error());

		$sql = "DELETE FROM $mainTable WHERE `id`='$orderID' ;";
		if (!mysql_query($sql)) die('Delete order Failed !' . mysql_error());

		echo "Order ID: ".$orderID." deleted<br>\n";
	
	}


?>