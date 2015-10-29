<?php  
   /*
   * db definition
   */ 
   
   	$hostname = 'localhost';
	$username = 'amosg';
	$password = "96e346nv932&";
	$appname = "Gilamos";

/*
	//  new ones:	 
	$clientid = '785966582104-ab859l88mtgu200tsssapjerfkeqgbri.apps.googleusercontent.com';
	$clientmail = '785966582104-ab859l88mtgu200tsssapjerfkeqgbri@developer.gserviceaccount.com';
	$clientkeypath = 'gilamos-f3d01bb7176e.p12';


	// old ones - use it to clean the calendars

	$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
	$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
	$clientkeypath = 'API Project-0ffd21d566b5.p12';
*/

	$mysql_id = @mysql_connect($hostname, $username, $password);
	if (!$mysql_id) {
		echo "MySql connection failed ! <br>";
	}
	
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
	
function selectDB($dbName)	{
	global $mysql_id, $globalDBName;
	
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
		global $lang, $logo, $custName;

	    $sql =  "SELECT * FROM customers.customers WHERE customers.customers.dbName='$DBname';";
	    $result = mysql_query($sql);
      	$customer = mysql_fetch_array($result);
      	$ssName = $customer["ssName"];
      	$lang = $customer["lang"];
      	$logo = $customer["logo"];
      	$custName = $customer["name"];

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

function authUserForm($user, $formID) {
	global $calendarsTable, $usersTable;
	
	$sql = "SELECT * FROM $calendarsTable WHERE formNumber='$formID';";
	$cal = mysql_query($sql) or die('get calendar-form Failed! ' . mysql_error()); 
	while ($calData = mysql_fetch_array($cal, MYSQL_ASSOC)) {	
		$calNum = $calData["number"];
		$sql = "SELECT * FROM $usersTable WHERE email='$user' AND calendarNum='$calNum';";
		$usr = mysql_query($sql) or die('get user data Failed! ' . mysql_error()); 
		if (mysql_num_rows($usr) > 0) {
			// found the calendar in the user table
			syslog(LOG_INFO, "User authorization approved: ".$user);
			return true;	
		}		
	}
	syslog(LOG_INFO, "User authorization failed: ".$user);
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
	