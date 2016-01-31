<?php  
   /*
   * Collect all Details from Angular HTTP Request.
   */ 
	require_once "mydb.php";

	$dbName = $_GET['db'];

	$user = $_GET['user'];
	//echo $dbName;
	if (!selectDB($dbName))
		return;			 
	
	getClientInfo($dbName);

	$list = [];
	$users = ['', $user];
	// First get all the fields
	foreach ($users as $owner) {

		$sql = "SELECT * FROM $searchTable WHERE user='$owner';";
		$result = mysql_query($sql) or die('Select search table Failed! ' . mysql_error()); 
		while ($search = mysql_fetch_assoc($result)) {

			if (!searchNameExist($list, $search["name"])) {

				$searchID = $search["id"];
				$calendar = [];
				$calendar["name"] = $search["calendar"];
				$calendar["startDate"] = $search["startDate"];
				$calendar["endDate"] = $search["endDate"];
				$search["calendar"] = $calendar;
				$filterList = [];
				$sql = "SELECT * FROM $filterTable WHERE searchID='$searchID';";
				$res = mysql_query($sql) or die('Select filter table Failed! ' . mysql_error()); 

				while ($filter = mysql_fetch_assoc($res)) {
					array_push($filterList, $filter);
				}
				$search["filterList"] = $filterList;

				$displayFields = [];
				$sql = "SELECT * FROM $displayFieldsTable WHERE searchID='$searchID';";
				$res = mysql_query($sql) or die('Select display fields table Failed! ' . mysql_error()); 

				while ($field = mysql_fetch_assoc($res)) {
					array_push($displayFields, $field["field"]);
				}
				$search["displayFields"] = $displayFields;

				array_push($list, $search);
				//var_dump($search
			}
		}
	}
	
	echo json_encode($list);	
		
	

function searchNameExist($list, $name) {
	foreach($list as $search) {
		if ($search["name"] == $name)
			return true;
	}
	return false;

}	

?>