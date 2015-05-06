<?php  
   /*
   * db definition
   */ 
   
   $hostname = 'localhost';
	$username = 'amosg';
	$password = "96e346nv932&";
	$appname = "Gilamos";
	//$databasename = "gilamos";  // "givaa"

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
?>
	