<?php  
   /*
   * db definition
   */ 
   
   $hostname = 'localhost';
	$username = 'amosg';
	$password = "96e346nv932&";
	$appname = "Gilamos";
	$databasename = "gilamos";  // "givaa"
	define("theSpreadsheet", "Take3", true);	// "SHIFTS_GIVAA"

	$mysql_id = @mysql_connect($hostname, $username, $password);
	if (!$mysql_id) {
		echo "MySql connection failed ! <br>";
	}
	$dbSelected = @mysql_select_db($databasename, $mysql_id);
	if (!$dbSelected) {
		echo "DB selection failed ! <br>";
	}
	mysql_set_charset("utf8");
	
	$mainTable = '_main';
	$formsTable = '_forms';
	$formFieldsTable = '_formFields';
	$fieldTable = '_fields';
	$listValueTable = '_listValues';
	$calendarsTable = '_calendars';
	$eventsTable = '_events';
	$usersTable = '_users';		
?>
	