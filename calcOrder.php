<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
	require_once "spreadsheet.php";
	
	//include_once "calendar.php";
	
   $postdata = file_get_contents("php://input");
   //echo "postdata: " . $postdata;

   $order = json_decode($postdata, true);

	$order = getCalcFields($order);  // get from spreadsheet

	$i = 0;
	foreach ($order as $field) {
		$value = $field["value"];
		$type = $field["type"];
		if ($type == "DATE") {
			// need to format the date returned from the spreadsheet
			$date = str_replace('/', '-', $value);
			if ($date != "00-00-0000" && $date = strtotime($date)) {
				$order[$i]["value"] = date('Y-m-d', $date);
			}
			else
				$order[$i]["value"] = "";
		}
		$i++;
	}
	echo json_encode($order);

	
 
?>