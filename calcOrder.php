<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";
	
	//include_once "calendar.php";
	
   $postdata = file_get_contents("php://input");
   //echo "postdata: " . $postdata;

   $data = json_decode($postdata, true);
  	$dbName = $data["dbName"]; 
	$origOrder = $data["order"];
	
	$ssName = getClientInfo($dbName);
	if ($ssName)
		setSSName($ssName);
	else {
		syslog(LOG_ERR, "Failed to get client spreadsheet");	
		return;
	}
	for ($i=0; initGoogleAPI($ssName) == null && $i<5; $i++)
		syslog(LOG_ERR, "InitGoogleApi Failed"); // retry 5 times to initialize

	$i=0;
	// call spreadsheet to calculate fields
	while (!($order = getCalcFields($origOrder)) && $i++ < 5) {
		// retry call to spreadsheet 
		syslog(LOG_ERR, "getCalcFields Failed. retrying...");
	}
	if (!$order) {
		syslog(LOG_ERR, "getCalcFields Failed after retry");
		echo "Failed to update spreadsheet. Please retry";
		return; 
	}

	$i = 0;
	foreach ($order as $field) {
		$value = $field["value"];
		$type = $field["type"];
		if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0) {
			$type = "DATETIME";  // it behaves like DATETIME
		}
		if ($type == "DATE" || $type == "DATETIME") {
			// need to format the date returned from the spreadsheet
			//echo " Date value before: ".$value."<br>\n";
			$date = str_replace('/', '-', $value);
			if ($date != "00-00-0000" && $date = strtotime($date)) {
				if ($type == "DATE")	
					$order[$i]["value"] = date('d-m-Y', $date);
				else 	// DATETIME
					$order[$i]["value"] = date('Y-m-d H:i', $date);
				//echo " Processed date: ".$order[$i]["value"]."<br>\n";
			}
		}
		$i++;
	}
	echo json_encode($order);

	
 
?>
