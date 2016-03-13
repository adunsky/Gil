
orderApp.service('userService', function () {
	var user = "";
	var db = "";

	function getUser() {
		return user;
	}

	function setUser(newUser) {
		user = newUser;					
	}

	return {
	    getUser: getUser,
	    setUser: setUser  
	}
});


orderApp.controller('configCtrl', function($scope, $http, $location, $rootScope){


		$scope.getUser = function() {
			gapi.client.load('oauth2', 'v2', function() {
				if (!gapi.client.oauth2)	// retry if not initialized yet
					setTimeout($scope.getUser, 1000);
			  gapi.client.oauth2.userinfo.get().execute(function(resp) {
			    // Get the user email
			    if (resp.email == "") {	// if no user is logged in - require the user to log in
			    	return;
			    }

			    if ($scope.user != resp.email) {	// user was changed
				    $scope.user = resp.email;
				}
				$scope.checkRole();
			  })
			});
		}

		$scope.authUser = function() {

			if (!gapi || !gapi.auth) {
				// wait until Google API library has loaded
				setTimeout($scope.authUser, 1000);
				return;
			}	

			try {
			    gapi.auth.authorize(
			        {'client_id': CLIENT_ID, 
			        'scope': SCOPES, 
			        'cookie_policy': 'single_host_origin',
			        'user_id': $scope.user,
			        'authuser': -1,
			        'immediate': true},
			        function(authResult) {
			        	if (authResult && !authResult.error) {
				       		// authorization granted
				       		$scope.checkRole();
						}
						else {
							// try manual authorization
							gapi.auth.authorize(
							    {'client_id': CLIENT_ID, 
							     'scope': SCOPES, 
							     'cookie_policy': 'single_host_origin',
							     'authuser': -1,
				   			     'immediate': false},
				   			    function(authResult) {
				   			       	if (authResult && !authResult.error) {
				   			       		// authorization granted
				   			       		$scope.getUser();
				   					}
				   					else {
						    			alert("Authorization failed !")
						    			return NULL;
						    		}	
	     					});
		     			}	
	        		});
				}
				catch (e) { 
				    alert(e.message); 
				}


		}


  	$scope.initCfg = function () {
 		
		var argv = $location.search();      		

		if (argv.db) {
			$scope.dbName = argv.db;
			$rootScope.dbName = $scope.dbName;
		}
		if (argv.user)
			$scope.user = argv.user;
		else {
			$scope.user = "";
		}

		console.log("user: "+$scope.user );
		$scope.authUser();

	}

	$scope.checkRole = function () {
		
 		$http.get("getUserRole.php", { params: { db: $scope.dbName, user: $scope.user }})
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	if (data.trim() != "admin") {
	      			alert("Error: "+$scope.user+" is not authorized to perform this action !");
	      			window.close();

	      		}
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        	}

        	$rootScope.user = $scope.user;

		});
	}

	$scope.updateCfg = function (cmd) {

		var host = window.location.hostname;
		window.open("http://"+host+"/stelvio/updateDB.php?cmd="+cmd+"&db="+$scope.dbName+"&user="+$scope.user);

	}

	$scope.updateCalendars = function () {
		var host = window.location.hostname;

		$location.path("/calendars");

	}

});


orderApp.controller('calendarsCtrl', function($scope, $http, $location, $rootScope) {	

	$scope.calendarList = null;
	$scope.fieldList = null;
	$scope.formList = null;

  	$scope.onMouseLeave = function() {
		document.body.style.cursor = 'default';
  	}

  	$scope.onMouseover = function() {
		document.body.style.cursor = 'pointer';
  	}

	$scope.initCalendars = function() {

		$scope.user = $rootScope.user;
		$scope.dbName = $rootScope.dbName;
		$scope.getCalendars();
		$scope.getFields();
		$scope.getForms();
		$scope.updateFieldNames();
		console.log("User: "+$scope.user);
	}

	$scope.getCalendars = function () {
 		$http.get("getCalendars.php", { params: { db: $scope.dbName, user: $scope.user, all: true } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.calendarList = angular.fromJson(data);

      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.calendarList = null;
    		}

    		//$scope.calcRoute();
		});
 	}

	$scope.getFields = function () {
 		$http.get("getFields.php", { params: { db: $scope.dbName } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.fieldList = angular.fromJson(data);
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.fieldList = null;
        		return;
    		}
    		$scope.getDateFieldList();
		});		

	}

	$scope.getForms = function () {
 		$http.get("getAllForms.php", { params: { db: $scope.dbName } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.formList = angular.fromJson(data);
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.formList = null;
        		return;
    		}
		});		

	}	

	$scope.getDateFieldList = function () {

		$scope.dateFieldList = [];
		$scope.noDateFieldList = [];
		for(var i=0; i < $scope.fieldList.length; i++) {
			var field = $scope.fieldList[i];
			if (field.type == 'DATE' || 
				field.type == 'DATETIME' ||
				field.type.indexOf('STARTTIME') == 0 ||
				field.type.indexOf('ENDTIME') == 0)
				// it's a date field
				$scope.dateFieldList.push(field);
			else	// no date field
				$scope.noDateFieldList.push(field);	


		}
	}

	$scope.updateFieldNames = function() {


		if (!$scope.calendarList || !$scope.fieldList || !$scope.formList) {
			// wait for lists to fill from the server
			setTimeout($scope.updateFieldNames, 1000);
			return;
		}

		for (var i=0; i<$scope.calendarList.length; i++) {
			var calendar = $scope.calendarList[i];
			calendar.fieldName = $scope.getFieldName(calendar.fieldIndex);
			calendar.titleName = $scope.getFieldName(calendar.titleField);
			calendar.locationName = $scope.getFieldName(calendar.locationField);
			calendar.participantsName = $scope.getFieldName(calendar.participants);
			calendar.formName = $scope.formList[calendar.formNumber-1].title;

		}
		$scope.$apply();

	}

	$scope.getFieldName = function(index) {
		if ($scope.fieldList && index > 1)	
			return $scope.fieldList[index-2].name;

		return "";

	}

	$scope.getFieldIndex = function(name) {
		for (var i=0; i < $scope.fieldList.length; i++) {
			if (name == $scope.fieldList[i].name)
				return $scope.fieldList[i].index;
		}
		return -1;

	}

	$scope.getFormNumber = function(title) {
		for (var i=0; i < $scope.formList.length; i++) {
			if (title == $scope.formList[i].title)
				return $scope.formList[i].number;
		}
		return -1;

	}

	$scope.getCalendarIndex = function(calendar) {
		for (var i=0; i < $scope.calendarList.length; i++) {
			if (calendar.number == $scope.calendarList[i].number)
				return i;
		}
		return -1;
	}

	$scope.removeCalendar = function(calendar) {
		if (calendar.calID == "") {	// new calendar - just remove from list
			$scope.calendarList.splice($scope.getCalendarIndex(calendar), 1);
			return;
		}
		if ($scope.countCalendar(calendar) > 1)
			var message = "This will delete the events for date field: "+calendar.fieldName+" in calendar: "+calendar.name;
		else
			var message = "WARNING: This will delete calendar: "+calendar.name+" and all it's events !!!";
			
		if (confirm(message)) {
			document.body.style.cursor = 'wait';
			$scope.inProgress = true;
			console.log("deleting calendar "+calendar.name);
			calendar.dbName = $scope.dbName;
			var content = angular.toJson(calendar);
			var request = $http({
			        method: "post",
			        url: "removeCalendar.php",
			        data: content,
			        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
			});
			    /* Check whether the HTTP Request is Successfull or not. */
			request.success(function (data) {
				$scope.calendarList.splice($scope.getCalendarIndex(calendar), 1);
				$scope.inProgress = false;
				document.body.style.cursor = 'default';
			});

			request.error(function (data, status) {
			    var message = data;
			    $scope.inProgress = false;
			    document.body.style.cursor = 'default';
			    alert("Error: "+message.trim());
			});
		}

	}

	$scope.addCalendar = function (calendar) {
		var newCalendar = {};
		newCalendar.name = calendar.name;
		newCalendar.locationField = calendar.locationField;
		newCalendar.titleField = calendar.titleField;
		newCalendar.participants = calendar.participants;
		newCalendar.fieldIndex = calendar.fieldIndex;
		newCalendar.formNumber = calendar.formNumber;

		newCalendar.calID = "";
		newCalendar.number = "";
		newCalendar.count = "";
		newCalendar.fieldName = "";
		newCalendar.titleName = "";
		newCalendar.nameChanged = true;
		newCalendar.dateChanged = true;		
		newCalendar.titleChanged = true;
		newCalendar.locationChanged = true;
		newCalendar.participantsChanged = true;
		newCalendar.formChanged = true;
		var newIndex = $scope.getCalendarIndex(calendar)+1;
		$scope.calendarList.splice(newIndex, 0, newCalendar);
		$scope.changed = true;

	}

	$scope.countCalendar = function(calendar) {
		var count = 0;
		for (var i=0; i < $scope.calendarList.length; i++) {
			if (calendar.name == $scope.calendarList[i].name)
				count++;

		}
		return count;

	}

	$scope.calChanged = function (calendar, param) {
		if (param == "name") {
			$scope.updateCalendarNames(calendar);
			calendar.nameChanged = true;
		}
		if (param == "date") {
			calendar.dateChanged = true;
			calendar.fieldIndex = $scope.getFieldIndex(calendar.fieldName);
		}
		if (param == "title") {
			calendar.titleChanged = true;
			calendar.titleField = $scope.getFieldIndex(calendar.titleName);
		}
		if (param == "location") {
			calendar.locationChanged = true;
			calendar.locationField = $scope.getFieldIndex(calendar.locationName);
		}
		if (param == "participants") {
			calendar.participantsChanged = true;
			calendar.participants = $scope.getFieldIndex(calendar.participantsName);
		}
		if (param == "form") {
			calendar.formChanged = true;
			calendar.formNumber = $scope.getFormNumber(calendar.formName);
		}

		$scope.changed = true;
	}

	$scope.updateCalendarNames = function (calendar) {
		for (var i=0; i < $scope.calendarList.length; i++) {
			if (calendar.CalID && calendar.calID == $scope.calendarList[i].calID) {
				$scope.calendarList[i].name = calendar.name;
				$scope.calendarList[i].nameChanged = true;
			}
		}
	}

	$scope.saveCalendars = function () {
		$scope.inProgress = true;
		document.body.style.cursor = 'wait';
		console.log("saving calendars");
		var data = {};
		data.dbName = $scope.dbName;
		data.calendarList = $scope.calendarList;
		var content = angular.toJson(data);
		var request = $http({
		        method: "post",
		        url: "updateCalendars.php",
		        data: content,
		        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
		});
		    /* Check whether the HTTP Request is Successfull or not. */
		request.success(function (data) {
			$scope.resetCalendars();
			$scope.inProgress = false;
			document.body.style.cursor = 'default';
			alert("Calendars updated successfully !");
		});

		request.error(function (data, status) {
		    var message = data;
		    $scope.inProgress = false;
		    document.body.style.cursor = 'default';
		    alert("Error: "+message.trim());
		});


	}

	$scope.resetCalendars = function () {
		for (var i=0; i<$scope.calendarList.length; i++) {
			var calendar = $scope.calendarList[i];
			calendar.nameChanged = false;
			calendar.dateChanged = false;		
			calendar.titleChanged = false;
			calendar.locationChanged = false;
			calendar.participantsChanged = false;
			calendar.formChanged = false;
		}
		$scope.changed = false;
	}

 	$scope.closeForm = function () {
 		var ok = true;
 		if ($scope.changed) {
 			ok = confirm("Close without saving ?");

 		}
 		if (ok) {
			$location.path("/config");
		}
 	}

});



