<?php  
   /*
   * db definition
   */ 
   
   	$hostname = 'localhost';
	$username = 'root';
	$password = "cyclist1";
	$appname = "Stelvio";
	$publicDNSName = "ec2-52-17-9-115.eu-west-1.compute.amazonaws.com";

	$logTable = '_log';
	$mainTable = '_main';
	$formsTable = '_forms';
	$formFieldsTable = '_formFields';
	$fieldTable = '_fields';
	$listValueTable = '_listValues';
	$calendarsTable = '_calendars';
	$eventsTable = '_events';
	$usersTable = '_users';	
	$emailCfgTable = '_emailCfg';	
	$emailsTable = '_emails';
	$clientIDTable = '_clientids';
	$searchTable = '_search';
	$filterTable = '_searchFilters';
	$displayFieldsTable = '_searchdisplayfields';

function closeDB() {
	global $mysql_id;
	
	if ($mysql_id)
		return(mysql_close($mysql_id));

	return false;
}


function selectDB($dbName)	{
	global $hostname, $username, $password, $globalDBName, $mysql_id;

	$mysql_id = @mysql_connect($hostname, $username, $password);
	if (!$mysql_id) {
		echo "MySql connection failed ! <br>";
		return false;
	}
	
	$dbSelected = @mysql_select_db($dbName, $mysql_id);
	if (!$dbSelected) {
		echo "DB selection failed ! <br>";
		return false;
	}
	$globalDBName = $dbName;
	mysql_set_charset("utf8");	
	return true;
}

 
function getClientList($DBName) {

	$sql = "SELECT * FROM customers.googleids WHERE customers.googleids.customerDBName='$DBName'";
	$result = mysql_query($sql) or die('Select client IDs table failed! ' . mysql_error());
	$clientList = [];
	while ($client = mysql_fetch_array($result)) {
		array_push($clientList, $client);
	}

	return $clientList;

} 

function getClientForCalendar($calNumber) {

	return ceil($calNumber/25);	// every 25 calendars use a new client ID

}

function getClient($DBName, $number) {

	$sql = "SELECT * FROM customers.googleids WHERE customers.googleids.customerDBName='$DBName' AND customers.googleids.clientNumber='$number'";
	$result = mysql_query($sql) or die('Select client IDs table failed! ' . mysql_error());
	$client = mysql_fetch_array($result);

	return $client;

} 

function getClientInfo($DBname) {
		global $lang, $logo, $custName, $useOldValues;

	    $sql =  "SELECT * FROM customers.customers WHERE customers.customers.dbName='$DBname';";
	    $result = mysql_query($sql);
      	$customer = mysql_fetch_array($result);
      	$ssName = $customer["ssName"];
      	$lang = $customer["lang"];
      	$logo = $customer["logo"];
      	$custName = $customer["name"];
      	if (array_key_exists("useOldValues", $customer))
      		$useOldValues = $customer["useOldValues"];
      	else
      		$useOldValues = 0;

      	return $ssName;
} 


function getUserRole($user) {
	global $usersTable;

	$sql = "SELECT * FROM $usersTable WHERE email='$user';";
	$res = mysql_query($sql) or die('get user data Failed! ' . mysql_error()); 
	if ($usr = mysql_fetch_array($res)) {
		return $usr['role'];
	}
	return "";
}

function authUserCalendar($user, $calName) {
	global $calendarsTable, $usersTable;
	
	// loop on all calendars with the same name
	$sql = "SELECT * FROM $calendarsTable WHERE name='$calName';";
	$calRes = mysql_query($sql) or die('get calendar name Failed! ' . mysql_error()); 
	while ($calByName = mysql_fetch_array($calRes, MYSQL_ASSOC)) {
		$calNum = $calByName["number"];	
		$sql = "SELECT * FROM $usersTable WHERE email='$user' AND calendarNum='$calNum';";
		$usr = mysql_query($sql) or die('get user data Failed! ' . mysql_error()); 
		if (mysql_num_rows($usr) > 0) {
			// found the calendar in the user table
			syslog(LOG_INFO, "User ".$user." authorization approved to calendar: ".$calName);
			return true;	
		}
	}		
	syslog(LOG_ERR, "User: ".$user." authorization to calendar: ".$calName." failed!");
	return false;
}

	
// check if field is unique in main table	
function isUnique($field, $orderID) {
	global $mainTable;

	$name = $field["index"];
	$value = $field["value"];

	if ($value == "")	// blank is allowed as non unique
		return true;

	$sql = "SELECT * FROM $mainTable WHERE `$name` = '$value';";
	$result = mysql_query($sql) or die('get order Failed! ' . mysql_error()); 
	if (mysql_num_rows($result) != 0) {
		if ($orderID <= 0)
		// unique field value already exists
			return false;

		$order = mysql_fetch_array($result);
		if ($order["id"] != $orderID)
			// unique field value already exists
			return false;

	}
	return true;

}
?>
	