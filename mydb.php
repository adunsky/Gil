<?php  
   /*
   * db definition
   */ 
   
   $hostname = 'localhost';
	$username = 'amosg';
	$password = "96e346nv932&";
	$appname = "Gilamos";

	//  new ones:	 
	$clientid = '785966582104-ab859l88mtgu200tsssapjerfkeqgbri.apps.googleusercontent.com';
	$clientmail = '785966582104-ab859l88mtgu200tsssapjerfkeqgbri@developer.gserviceaccount.com';
	$clientkeypath = 'gilamos-f3d01bb7176e.p12';


	// old ones - use it to clean the calendars
 /*
	$clientid = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u.apps.googleusercontent.com';
	$clientmail = '457875993449-48gmkqssiulu00va3vtrlvg297pv1j8u@developer.gserviceaccount.com';
	$clientkeypath = 'API Project-0ffd21d566b5.p12';
*/

	$mysql_id = @mysql_connect($hostname, $username, $password);
	if (!$mysql_id) {
		echo "MySql connection failed ! <br>";
	}
	
	$mainTable = '_main';
	$formsTable = '_forms';
	$formFieldsTable = '_formFields';
	$fieldTable = '_fields';
	$listValueTable = '_listValues';
	$calendarsTable = '_calendars';
	$eventsTable = '_events';
	$usersTable = '_users';	
	
	
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


?>
	