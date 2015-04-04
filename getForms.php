<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";
		 
   //var_dump($_GET);
	
	$sql = "SELECT * FROM $formsTable;";
	$result = mysql_query($sql) or die('get fields Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) > 0)	{
		//  found forms
		$forms = [];
		while ($form = mysql_fetch_array($result)) {
			$formID = $form["number"];
			
			$fieldsql = "SELECT * FROM $formFieldsTable WHERE formNumber = '$formID';";
			$fieldresult = mysql_query($fieldsql) or die('get fields Failed! ' . mysql_error()); 
			if (mysql_num_rows($fieldresult) > 0)	{
				//  found fields
				$fields = [];
				while ($field = mysql_fetch_array($fieldresult)) {
					$fieldID = $field["fieldIndex"];
					
					$fieldsql = "SELECT * FROM $fieldTable WHERE `index` = '$fieldID';";	
					$fieldetails = mysql_query($fieldsql) or die('get field details Failed! ' . mysql_error()); 
					if (mysql_num_rows($fieldetails) > 0)	{
						$fieldata = mysql_fetch_array($fieldetails);
						$field["name"] = $fieldata["name"];
						$field["type"] = $fieldata["type"];
					}
					array_push($fields, $field);
				}
				$form["fields"] = $fields;
			}
			else {
				echo " Fields not found for form" ;
			}
			array_push($forms, $form);	
		}
	}
	else {
		echo " Forms not found in forms table" ;
	}
			
	echo json_encode($forms);	
	

?>