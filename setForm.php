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
	$form = $data["form"];
	$formID = $form["number"];
	// var_dump($form);

	if (!selectDB($dbName)) {
		syslog(LOG_ERR, "Failed to select client DB: "+$dbName);
		return;	
	}

	// First remove the old form configuration from the table
	$sql = "DELETE FROM $formFieldsTable WHERE formNumber = '$formID';";
	$result = mysql_query($sql) or die('Delete form fields Failed! ' . mysql_error()); 

	foreach ($form["fields"] as $field) {
		if (array_key_exists("fieldIndex", $field)) {
			$index = $field["fieldIndex"];
			$type = $field["fieldType"];
			$col = $field["col"];
			$values = "'$formID', '$index', '$type', '$col'";
			$sql = "INSERT INTO $formFieldsTable VALUES ($values)";
			$result = mysql_query($sql) or die('Insert to form fields table Failed! ' . mysql_error()); 
		}
	}
	syslog (LOG_INFO, "Form update complete: ".$form["title"]);

 
?>