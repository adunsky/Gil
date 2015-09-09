<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	$formID = $_GET['form'];
	$formID++;	// form number starts at 1

	$user = $_GET['user'];
	getClientInfo($dbName);

	$role = getUserRole($user);

	$sql = "SELECT * FROM $formsTable WHERE number=$formID;";
	$result = mysql_query($sql) or die('get form Failed! ' . mysql_error()); 
	if ($form = mysql_fetch_array($result))	{
		//  found form
		$form["logo"] = $logo;
		$form["role"] = $role;
		if ($lang == 'eng') // Left to right form
			$form["dir"] = "ltr";
		else  // right to left form
			$form["dir"] = "rtl";

		$fieldsql = "SELECT * FROM $formFieldsTable WHERE formNumber = '$formID';";
		$fieldresult = mysql_query($fieldsql) or die('get fields Failed! ' . mysql_error()); 
		if (mysql_num_rows($fieldresult) > 0)	{
			//  found fields
			$fields = [];
			while ($field = mysql_fetch_array($fieldresult, MYSQL_ASSOC)) {
				$fieldID = $field["fieldIndex"];
				
				$fieldsql = "SELECT * FROM $fieldTable WHERE `index` = '$fieldID';";	
				$fieldetails = mysql_query($fieldsql) or die('get field details Failed! ' . mysql_error()); 
				if (mysql_num_rows($fieldetails) > 0)	{
					$fieldata = mysql_fetch_array($fieldetails, MYSQL_ASSOC);
					$field["name"] = $fieldata["name"];
					$field["type"] = $fieldata["type"];
					$field["input"] = $fieldata["input"];
					$field["default"] = $fieldata["default"];
					$type = $field["type"];
					// convert STARTTIME and ENDTIME to DATETIME		
					if (strpos($type, "STARTTIME") === 0 || strpos($type, "ENDTIME") === 0) {
						//echo "found start/end time";
						$type = "DATETIME";  // it behaves like DATETIME
						$field["type"] = $type;
					}						
					if ($type == "LIST") {
						// get the list values
						$field["listValues"] = [];
						$sql = "SELECT * FROM $listValueTable WHERE `index` = '$fieldID';";
						$res = mysql_query($sql) or die('get list values Failed! ' . mysql_error()); 
						while ($listValue = mysql_fetch_array($res, MYSQL_ASSOC)) {
							array_push($field["listValues"], $listValue["value"]);	
						}
					}						
				}
				array_push($fields, $field);
			}
			$form["fields"] = $fields;
		}
		else {
			echo " Fields not found for form" ;
		}
	}
	else {
		echo " Form not found in forms table" ;
	}
			
	echo json_encode($form);	
	

?>