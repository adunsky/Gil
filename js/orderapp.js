var orderApp = angular.module('orderApp', ['ngRoute', 'ngDraggable', 'ui.bootstrap', 'ui.bootstrap.datetimepicker']);

orderApp.factory('myService', function () {
	var Order = [];
	var Forms = [];

	function getOrder() {
		return Order;
	}

	function setOrder(order) {
		Order = [];
		for (i=0; order[i] != null; i++) {
			order[i].dateTimeCalendarisOpen = false;
			Order.push(order[i]);
		}	
						
	}
	function getForms() {
		return Forms;
	}

	function setForms(forms) {
		Forms = [];
		for (i=0; forms[i] != null; i++) {
			Forms.push(forms[i]);
		}	
						
	}
	
	return {
	    getOrder: getOrder,
	    setOrder: setOrder,
	    getForms: getForms,
	    setForms: setForms	    
	}
});

orderApp.config(function($routeProvider){

      $routeProvider
        .when('/',{
                templateUrl: 'home.html'
          })
        .when('/newOrder',{
                templateUrl: 'newOrder.html'
          })
        .when('/route',{
                templateUrl: 'route.html'
          });

});


orderApp.controller('routeCtrl', function($scope, $http,  $location, myService){

	$scope.filterList = [];
	$scope.optimize = true;
	$scope.calculated = false;
  	
	$scope.onDragComplete = function(order,$event) {
		var sourceIndex = $scope.dirList.indexOf(order);
		$scope.dirList.splice(sourceIndex, 1);	// remove the dragged item

	}

	$scope.openOrder = function(order) {
		if (order.orderID && order.calendarID)
			window.open("http://localhost/Gilamos/#/newOrder?db="+$scope.dbName+"&orderID="+order.orderID+"&calendarNum="+order.calendarID);

	}

	$scope.optimizeRoute = function() {
		if ($scope.optimize)
			$scope.calcRoute();

	}


	$scope.onDropComplete = function(targetOrder, order, $event) {

		var targetIndex = $scope.dirList.indexOf(targetOrder);
		$scope.dirList.splice(targetIndex, 0, order);	// add the dragged item
		$scope.optimize = false;
		if ($scope.calculated)	// recalculate if it was already calculated
			$scope.calcRoute();
	}

  	$scope.getRoute = function () {
 		
		var argv = $location.search();      		

		if (argv.db)
			$scope.dbName = argv.db;
		if (argv.user)
				$scope.user = argv.user;
			else {
				$scope.user = ""; // $scope.getUser();
			}

		if (argv.start)
			$scope.startDate = argv.start;		
		if (argv.end)
			$scope.endDate = argv.end;	
		if (argv.calendars)
			$scope.calendars = argv.calendars;

 		$http.get("getOrders.php", { params: { db: $scope.dbName, user: $scope.user, startDate: $scope.startDate, endDate: $scope.endDate, calendars: $scope.calendars, filters: "" } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.orderList = angular.fromJson(data);
	        	$scope.dirList = $scope.orderList;
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.orderList = null;
    		}
    		//$scope.calcRoute();
		});

		var mapOptions = {
		        center: { lat: 32, lng: 34.78}, // Tel Aviv
		        zoom: 8
		    };
		
		$scope.map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
		$scope.directionsService = new google.maps.DirectionsService();
		var rendererOptions = {
		  draggable: true
		};
		$scope.directionsDisplay = new google.maps.DirectionsRenderer(rendererOptions);
	  	$scope.directionsDisplay.setMap($scope.map);
	  	$scope.directionsDisplay.setPanel(document.getElementById('dirPanel'));
	  	google.maps.event.addListener($scope.directionsDisplay, 'directions_changed', 
	  		function() {
    			$scope.computeTotalDistance($scope.directionsDisplay.getDirections());
			});

	  	// listen for the window resize event & trigger Google Maps to update too
	  	$(window).resize(function() {
	  	  	google.maps.event.trigger($scope.map, "resize");
	  	});

	  	$scope.getSearchFields();
	}

	$scope.calcRoute = function () {

		$scope.message = "";
		$scope.calculated = true;

		if (!$scope.startAddress || $scope.startAddress == "") {
			alert("Please enter start address");
			return;
		}
	  	var start = $scope.startAddress;
	  	if (!$scope.endAddress || $scope.endAddress == "")
	  		$scope.endAddress = $scope.startAddress;
	  	var end = $scope.endAddress;

	  	var waypts = [];
	  	for (var i = 0; i < $scope.dirList.length ; i++) {
	  		while ($scope.dirList[i] && (!$scope.dirList[i].location || $scope.dirList[i].location == "")) {
	  			var orderID = $scope.dirList[i].orderID ? $scope.dirList[i].orderID : "";
	  			$scope.dirList.splice(i, 1);	// remove empty addresses from the list
	  			if (orderID != "")
	  				$scope.message += "\nOrder "+orderID+" has no address - removed from the list";
	  		}
	  		if ($scope.dirList[i] && $scope.dirList[i].location)
		  		waypts.push({
		  	   		location: $scope.dirList[i].location,
		  	   		stopover: true});
	  	}

	  	var request = {
	  		origin:start,
	    	destination:end,
	    	waypoints: waypts,
	    	optimizeWaypoints: $scope.optimize,
	    	travelMode: google.maps.TravelMode.DRIVING
	  	};

	  	$scope.directionsService.route(request, function(result, status) {
	    	if (status == google.maps.DirectionsStatus.OK) {
	      		$scope.directionsDisplay.setDirections(result);
	      		$scope.startIcon = mapIconsPath + mapIcons[0];
	      		var tmpList = [];
				var route = result.routes[0];
			    // For each route, display summary information.
			    for (var i = 0; i < route.legs.length; i++) {
			    	if (i < $scope.dirList.length) {
			    		tmpList[i] = $scope.dirList[route.waypoint_order[i]];
			    		tmpList[i].img = mapIconsPath + mapIcons[i+1];
			    	}
			    	else
			    		tmpList[i] = {};		// the last leg
			    	
			    	tmpList[i].distance = route.legs[i].distance.text;
			    	tmpList[i].duration = " - about "+route.legs[i].duration.text;
			    }
			   	$scope.endIcon = mapIconsPath + mapIcons[i];
			   	$scope.dirList = tmpList;	// update the direction list
	    	}
	    	else {
	    		if (status == "ZERO_RESULTS")
	    			status = "Invalid address. Please verify that all addreses are valid";
	    		alert("Error: "+status);
	    		$scope.directionsDisplay.set('directions', null);
	    	}
   		  	$scope.$apply();
	  	});
	}

	$scope.computeTotalDistance = function (result) {
	  	var total = 0;
	  	if (!result)
	  		document.getElementById('total').innerHTML = "";
	  	else {
		  	var myroute = result.routes[0];
		  	for (var i = 0; i < myroute.legs.length; i++) {
		    	total += myroute.legs[i].distance.value;
		  	}
		  	total = Math.round(total / 1000.0);
		  	document.getElementById('total').innerHTML = total + ' km';
	  	}
	}

	$scope.getSearchFields = function () {
 		$http.get("getSearchFields.php", { params: { db: $scope.dbName } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.fieldList = angular.fromJson(data);
	        	// add empty string
	        	var field = {};
	        	field.name = "";
	        	field.value = "";
	        	$scope.fieldList.push(field);
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.fieldList = null;
    		}
    		$scope.addFilter();
		});		

	}

	$scope.addFilter = function (filter) {

		var filterLen = $scope.filterList.length;
		var filterIndex = $scope.filterList.indexOf(filter);

		if (filterIndex == filterLen-1)
			$scope.filterList[filterLen] = {};		// add another filter field

		if (filter && filter.name == "")	// no value selected - remove it
			$scope.filterList.splice(filterIndex, 1);

	}

	$scope.getFilter = function () {

		var filters = angular.toJson($scope.filterList);		
 		$http.get("getOrders.php", { params: { db: $scope.dbName, user: $scope.user, startDate: $scope.startDate, endDate: $scope.endDate, calendars: $scope.calendars, filters: filters } })
 		.success(function(data) {
         	console.log(data);
  	    	try {
	        	$scope.orderList = angular.fromJson(data);
	        	$scope.dirList = $scope.orderList;
      	 	}
    		catch (e) {
        		alert("Error: "+data);
        		$scope.orderList = null;
    		}
    		$scope.calculated = false;
    		$scope.startIcon = null;
    		$scope.endIcon = null;
    		$scope.message = "";
    		$scope.directionsDisplay.set('directions', null);
		});


	}

});


// Forms controller
orderApp.controller('orderCtrl', function($scope, $http, $timeout, $sce, $location, myService){
		$scope.order = myService.getOrder();
		$scope.inProgress = false;

      	$scope.getOrder = function () {
     		
		   	var eventID, calendarNum;
			
			var argv = $location.search();      		
			if (argv.id)
				eventID = argv.id;
			else
				eventID = 0;
			if (argv.calendarNum)
				calendarNum = argv.calendarNum;
			else
				calendarNum = 0;
			if (argv.orderID)
				$scope.orderID = argv.orderID;
			else
				$scope.orderID = 0;
			if (argv.db)
				$scope.dbName = argv.db;
			if (argv.user)
 				$scope.user = argv.user;
 			else {
 				$scope.user = ""; // $scope.getUser();
 			}		

     		$http.get("getOrder.php", { params: { eventID: eventID, db: $scope.dbName, calendarNum: calendarNum, orderID: $scope.orderID, user: $scope.user } })
     		.success(function(data) {
             $scope.message = data;
             console.log($scope.message);
      	    try {
   	        		$updatedOrder = angular.fromJson(data);
   	        		if ($updatedOrder) {
   	        			$scope.orderID = $updatedOrder.orderID;
   	        			$scope.order = $updatedOrder.order;
             			myService.setOrder($scope.order);
             			$scope.getFormFields($updatedOrder.formID);
					}
	      	 }
        		 catch (e) {
            		alert("Error: "+$scope.message);
            		myService.setOrder(null);
        		 } 
			}); 
		};
		
			
      	$scope.getFormFields = function (form) {
				if (!$scope.forms) {
		     		$http.get("getForms.php", { params: { db: $scope.dbName } })
		     		.success(function(data) {
		             $scope.message = data;
		             console.log($scope.message);
		      	    try {
		   	        		$scope.forms = angular.fromJson(data);
		   	        		if ($scope.forms) {
		             			myService.setForms($scope.forms);
		             			$scope.form = $scope.forms[form];
		             			$scope.setFormValues();

								}
			      	 }
		        		 catch (e) {
		            		alert("Error: "+$scope.message);
		            		myService.setForms(null);
		        		 } 
	
					}); 
				}
				else { // form exist - set it to current form and set it's values
					$scope.form = $scope.forms[form];				
					$scope.setFormValues();
				}

			};
	
			setProgress = function() {
				$scope.progress += 5;
				if ($scope.inProgress)
					$timeout(setProgress, 200);
			
			}
			
			getTimezoneOffset = function () {
				var offset = new Date().getTimezoneOffset()/60;
			    var hours = Math.abs(parseInt(offset));
			    var minutes = (Math.abs(offset) - hours)*60;

			    if (offset > 0) // positive
			    	var sign="-";
			    else
			    	var sign="+";

			    if (hours   < 10) {hours   = "0"+hours;}
			    if (minutes < 10) {minutes = "0"+minutes;}

			    var time    = sign+hours+':'+minutes;
			    return time;
			}	

			$scope.setFormValues = function()	{
					if ($scope.order) 
					for(var i=0; $scope.form && $scope.form.fields[i]; i++) {
		      			var fieldIndex = $scope.form.fields[i].fieldIndex-2;
		      			$scope.form.fields[i].input = $scope.order[fieldIndex].input;
		      			if ($scope.form.fields[i].type == 'EmbedHyperlink')
		      				$scope.form.fields[i].value = $sce.trustAsResourceUrl($scope.order[fieldIndex].value);
		      			else {
		      				if ($scope.form.fields[i].type == 'Email') {
								$scope.form.fields[i].prefix = "https://mail.google.com/mail?view=cm&to=";		      					
		      				}
		      				else {
		      					if ($scope.form.fields[i].type == 'Hyperlink') {
		      						$scope.form.fields[i].prefix = "http://";		      					
		      					}
		      				}		      					
		      				$scope.form.fields[i].value = $scope.order[fieldIndex].value;
						}
	    				if ($scope.form.fields[i].type == 'LIST') {
							// add the current value to the list if it is not there
							if ($scope.form.fields[i].listValues.indexOf($scope.form.fields[i].value) == -1)
								$scope.form.fields[i].listValues.push($scope.form.fields[i].value);	    				
	    					// add an empty string to the list if not already there
							if ($scope.form.fields[i].value != "")		    					
	    						$scope.form.fields[i].listValues.push("");
	    				}
	    				if ($scope.form.fields[i].type == 'DATETIME' && $scope.form.fields[i].fieldType == "Edit") {
	    					// the control expects format yyyy-mm-ddThh:mm+timezoneOffset
	    					var date = new Date($scope.form.fields[i].value);
	    					if ($scope.form.fields[i].value && $scope.form.fields[i].value != "")
	    						$scope.form.fields[i].value = $scope.form.fields[i].value.replace(" ", "T") + getTimezoneOffset();
	    				}	    				  				
		      				
		      		}    		
			}
			
			
			$scope.openDateTimeCalendar = function(e, field) {
			    e.preventDefault();
			    e.stopPropagation();
			
			    field.dateTimeCalendarisOpen = true;
			};
      
			$scope.timeOptions = {
			    //readonlyInput: true,
			    startingTime: "08:00",
			    showMeridian: false
			};
			$scope.dateOptions = {
			    //readonlyInput: true,
			    showWeeks: false
			};

	      $scope.updateOrder = function () {
	      		document.body.style.cursor = 'wait';
	      		$scope.progress = 0;
					$scope.inProgress = true;
	      		setProgress(); 
	      		$scope.updateValues(); 
	      		// get the order ID and send to PHP
				$updatedOrder = {};
				$updatedOrder.dbName = $scope.dbName;						
				$updatedOrder.order = $scope.order;	      		
				$updatedOrder.orderID = $scope.orderID;	      		
				$updatedOrder.user = $scope.user;	      		
	      		
	            var content = angular.toJson($updatedOrder);
	            var request = $http({
	                    method: "post",
	                    url: "updateOrder.php",
	                    data: content,
	                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
	            });
	                /* Check whether the HTTP Request is Successfull or not. */
	            request.success(function (data) {
	                $scope.message = data;
	                console.log($scope.message);
					if (!isNaN(data)) {	                
	                	$scope.orderID = parseInt(data); // PHP returned a valid ID number
	                	if ($scope.newDriveFolder && $scope.folderID) {
	                		// rename the temporary drive folder
	                		$scope.authRenameFolder($scope.folderID, FOLDER_PREFIX + $scope.orderID);
	                	}
	                	else {
	                		$scope.close();
  						}               	
	                }
	                else {
	                	//$scope.orderID = data; // PHP returned invalid ID number
	                	$scope.inProgress = false;
	                	alert("Error: "+$scope.message);
	                }	
	            });
	            request.error(function (data, status) {
	                $scope.message = data;
	                $scope.inProgress = false;
	                alert("Error: "+$scope.message);
	            });
	      }   
         
	      	$scope.updateValues = function () {
	      		for(var i=0; $scope.form.fields[i] != null; i++) {
	      			var fieldIndex = $scope.form.fields[i].fieldIndex-2;
	      			$scope.order[fieldIndex].value = $scope.form.fields[i].value;
	      		}         
			}

			$scope.openLink = function(e, field) {

				window.open(field.prefix+field.value, '_blank');
				e.preventDefault();
				return false;
			}

			$scope.checkUnique = function(field) {

				$scope.validate(field);
				if (field.input == 'U' && field.value != "")	{	// check if unique value already exist in DB
		     		$http.get("checkUnique.php", { params: { orderID: $scope.orderID, db: $scope.dbName, index: field.fieldIndex, value: field.value } })
		     		.success(function(data) {
		             $scope.message = data;
		             console.log($scope.message);
		             if (data.trim() == "false") {
		             	// not unique
		             	field.error = true;
		             	if ($scope.form.dir == 'rtl')
		             		field.message = field.name+" "+field.value+" כבר קיים במערכת ";
		             	else
		             		field.message = field.name+" "+field.value+" already exists";						
		             }
		             else
		             	field.error = false;	
					}); 
				}
			}

			$scope.validate = function(field) {
				var i = 0
				// First check if mandatory field is empty
				if (field.fieldType == "Mandatory" && (field.value == null || field.value == "")) {
					// Mark field error
					field.error = true;
					if ($scope.form.dir == 'rtl')
						field.message = "חובה למלא שדה זה";
					else
						field.message = "This field is required";
					return field.value;
				}
				else
					field.error = false;

				if (!field.value || field.value == "")
					return "";

				// do not allow '=' or '+' at the begining of an input text due to the spreadsheet limitation
				while (field.value[i] && field.value[i] != "" && (field.value[i] == '=' || field.value[i] == '+'))
					i++; // proceed until no '=' or '+' at the beginning 

				return field.value.substring(i, field.value.length);	

			}
  
  			$scope.errorInForm = function() {
  				// return true if manadtory field is empty
  				for(var i=0; $scope.form && $scope.form.fields[i]; i++) {
  					if ($scope.form.fields[i].fieldType == 'Mandatory' && 
  						($scope.form.fields[i].value == null || $scope.form.fields[i].value == "")) {
  						$scope.form.error = true;

						if ($scope.form.dir == 'rtl')
							$scope.form.message = "שדות חובה חסרים";
						else
  							$scope.form.message = "Missing required fields";
  						return true;
  					}
  				}
  				$scope.form.error = false;
  				return false;
  			}

  			$scope.filterEditMandatory = function(field) {
  				return (field.fieldType == 'Edit' || field.fieldType == 'Mandatory');
  			}

  			$scope.isRequired = function(field) {
  				if (field.fieldType=='Mandatory')
  					return 'required';
  			}

	    	$scope.calcOrder = function () {
	      		document.body.style.cursor = 'wait';
	      		$scope.progress = 0;
					$scope.inProgress = true;
	      		setProgress(); 	      		
	      		$scope.updateValues(); 
					$updatedOrder = {};
					$updatedOrder.dbName = $scope.dbName;						
					$updatedOrder.order = $scope.order;	      		
	      		
	            var content = angular.toJson($updatedOrder);
	            var request = $http({
	                    method: "post",
	                    url: "calcOrder.php",
	                    data: content,
	                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
	            });
	                /* Check whether the HTTP Request is Successfull or not. */
	            request.success(function (data) {
	               $scope.message = data;
	               console.log($scope.message);
	               try {
							$scope.order = angular.fromJson(data);
							$scope.setFormValues();
		            	myService.setOrder($scope.order);
		            }						
						catch (e) {
							alert("Error: "+$scope.message);
						}						
						document.body.style.cursor = 'default';
		            $scope.inProgress = false;	
	            });
	            request.error(function (data, status) {
	                $scope.message = data;
	                $scope.inProgress = false;
						 document.body.style.cursor = 'default';	                
	                alert($scope.message);
	            });

			};

			$scope.getUserInfo = function() {
				gapi.client.load('oauth2', 'v2', function() {
				  gapi.client.oauth2.userinfo.get().execute(function(resp) {
				    // Get the user email
				    $scope.user = resp.email;
				  })
				});
			}

			$scope.getUser = function() {
				if ($scope.user && $scope.user != "")
					// user already set (from gadget)
					return;

				if (!gapi || !gapi.auth) {
					// wait until Google API library has loaded
					window.setTimeout($scope.getUser, 1000);
					return;
				}	
				try {
				    gapi.auth.authorize(
				        {'client_id': CLIENT_ID, 
				        'scope': 'https://www.googleapis.com/auth/userinfo.email', 
				        'cookie_policy': 'single_host_origin',
				        //'authuser': -1,
				        'immediate': true},
				        function(authResult) {
				        	if (authResult && !authResult.error) {
					       		// authorization granted
					       		$scope.getUserInfo();
							}
							else {
								// try manual authorization
								gapi.auth.authorize(
								    {'client_id': CLIENT_ID, 
								     'scope': 'https://www.googleapis.com/auth/userinfo.email', 
								     'cookie_policy': 'single_host_origin',
					   			     'immediate': false},
					   			    function(authResult) {
					   			       	if (authResult && !authResult.error) {
					   			       		// authorization granted
					   			       		$scope.getUserInfo();
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


			// Code for file attachments

			var CLIENT_ID = '785966582104-p03j542fcviuklf0kka21ushko2i7k0a.apps.googleusercontent.com';
			var SCOPES = 'https://www.googleapis.com/auth/drive';
			var FOLDER_PREFIX = '';
			
   			$scope.initUpload = function(event){
   			var files = null;
   			if (event)	
   				files = event.target.files;

   			if ($scope.folderID) {
   				// Already authorized
   				$scope.uploadFiles(files, $scope.orderID);
   				return;
   			}
   			/**
   			 * Check if the current user has authorized to upload to the drive
   			 */
   			try {
   			    gapi.auth.authorize(
   			        {'client_id': CLIENT_ID, 
   			        'scope': SCOPES, 
   			        'cookie_policy': 'single_host_origin',
   			        'authuser': -1,
   			        'immediate': true},
   			        function(authResult) {
   			        	if (authResult && !authResult.error) {
   				       		// authorization granted
   				       		$scope.uploadFiles(files, $scope.orderID);
   						}
   						else {
   							// try manual authorization
   							gapi.auth.authorize(
   							    {'client_id': CLIENT_ID, 
   							     'scope': SCOPES, 
   							     'cookie_policy': 'single_host_origin',
   				   			     'immediate': false},
   				   			    function(authResult) {
   				   			       	if (authResult && !authResult.error) {
   				   			       		// authorization granted
   				   			       		$scope.uploadFiles(files, $scope.orderID);
   				   					}
   				   					else {
   						    			alert("Authorization failed !")
   						    		}	
		     					});
   		     			}	
		        	});
   				}
   				catch (e) { 
   				    alert(e.message); 
   				}

   			};

			$scope.setFolderID = function(folderID) {

				$scope.folderID = folderID;
				if ($scope.orderID <= 0)
					// new folder
					$scope.newDriveFolder = true;
			}

			$scope.openFolder = function()
			{
        		if (!$scope.folderID) {
        			// simulate file upload just to get the folder ID from Google drive
        			$scope.initUpload(null);
        		}
        		$scope.openFolderWindow()
			}


			$scope.openFolderWindow = function()
			{
				if (!$scope.folderID)
					window.setTimeout($scope.openFolderWindow, 1000);
				else
					window.open("https://drive.google.com/folderview?id="+$scope.folderID);
			}   


			$scope.selectFile = function()
			{
				$("#file").click();

			}

			/**
			 * Start the file upload.
			 *
			 * @param {Object} evt Arguments from the file selector.
			 */
			$scope.uploadFiles = function(files, orderID) {
			  	gapi.client.load('drive', 'v2', function() {
			  		if ($scope.folderID) {
			  			// folder already found - insert into it
						$scope.insertFiles(files, $scope.folderID);
			  		}
			  		else	// need to find or create the parent folder
			        	$scope.insertToParentFolder(orderID, files);
			    });
			}


			$scope.insertToParentFolder = function(orderID, files) {
			  var parentFolder = 'GoogMesh';
			  // Search if GoogMesh folder exists
			  $qString = "title = '"+parentFolder+"' and trashed=false and mimeType='application/vnd.google-apps.folder' and sharedWithMe";
			  gapi.client.drive.files.list({
			    'q' : $qString
			    }).
			    execute(function(resp) {
			      if (resp.items && resp.items[0])  {
			        // GoogMesh folder exists - insert into it
			        $scope.insertToFolder(resp.items[0].id, orderID, files);

			      }
			      else {
			      		// Folder is not shared with the user - alert and quit
			      	 	alert("Folder "+parentFolder+" is not shared with this user ");
			      	 	/*
			          // folder doesn't exist - create it
			          var request = gapi.client.request({
			              'path': '/drive/v2/files/',
			              'method': 'POST',
			              'headers': {
			                  'Content-Type': 'application/json',
			                  //'Authorization': 'Bearer ' + access_token,             
			              },
			              'body':{
			                  "title" : parentFolder,
			                  "mimeType" : "application/vnd.google-apps.folder",
			              }
			          }).
			          execute(function(file) {
			            if (file)  {
			              // GoogMesh folder exists - insert into it
			              $scope.insertToFolder(file.id, orderID, files);

			            }
			          });  
			          */    
			      }
			    });
			}

			$scope.insertToFolder = function(parentID, orderID, files, callback) {
			  var folderName = FOLDER_PREFIX+orderID;
			  // Search if folder exists
			  $qString = "title = '"+folderName+"'"+" and trashed = false and mimeType = 'application/vnd.google-apps.folder'";
			  gapi.client.drive.children.list({
			    'folderId' : parentID, 
			    'q' : $qString
			    }).
			    execute(function(resp) {
			      if (resp.items && resp.items[0])  {
			      	if (orderID <= 0) {
						// Temporary folder is in use - try a different one with --orderID
						$scope.insertToFolder(parentID, --$scope.orderID, files);
						return;
			      	}
			        // folder exist - insert into it
			        if (files)
			          	$scope.insertFiles(files, resp.items[0].id);
			          
			          $scope.setFolderID(resp.items[0].id);
			      }
			      else {

			        // folder doesn't exist - create it
			        var request = gapi.client.request({
			            'path': '/drive/v2/files/',
			            'method': 'POST',
			            'headers': {
			                'Content-Type': 'application/json',
			                //'Authorization': 'Bearer ' + access_token,             
			            },
			            'body':{
			                "title" : folderName,
			                'parents': [{"id": parentID}],
			                "mimeType" : "application/vnd.google-apps.folder",
			            }
			        });
			        if (!callback) {
			            callback = function(file) {
			              	// folder created - insert into it
		              		if (files) 
		              			$scope.insertFiles(files, file.id);
				            
				            $scope.setFolderID(file.id);
				            console.log("Folder: ");
			    	        console.log(file);              
			            };
			        }
			        request.execute(callback);
			      }
			    });
			      
			  
			}


			$scope.insertFiles = function(files, parentID) {
				$fileNames = "";
				$count = 0;
				for ($i=0 ; $i < files.length ; $i++) {
					$scope.insertFile(files[$i], parentID, function(file) {
						$scope.filesUploaded = true;
						console.log("File: ");
						console.log(file);
						$fileNames += file.originalFilename + "\n";
						if (++$count == files.length)
							// This is the last file uploaded
						  	alert("Files uploaded to Google drive:\n"+$fileNames);

						});

				}

			}


			/**
			 * Insert new file.
			 *
			 * @param {File} fileData File object to read data from.
			 * @param {Function} callback Function to call when the request is complete.
			 */
			$scope.insertFile = function(fileData, parentID, callback) {
				const boundary = '-------314159265358979323846';
				const delimiter = "\r\n--" + boundary + "\r\n";
				const close_delim = "\r\n--" + boundary + "--";

				var reader = new FileReader();
				reader.readAsBinaryString(fileData);
				reader.onload = function(e) {
					var contentType = fileData.type || 'application/octet-stream';
				  	var metadata = {
				    	'title': fileData.name,
				    	'parents': [{"id": parentID}],
				    	'mimeType': contentType
				    };

				    var base64Data = btoa(reader.result);
				    var multipartRequestBody =
				        delimiter +
				        'Content-Type: application/json\r\n\r\n' +
				        JSON.stringify(metadata) +
				        delimiter +
				        'Content-Type: ' + contentType + '\r\n' +
				        'Content-Transfer-Encoding: base64\r\n' +
				        '\r\n' +
				        base64Data +
				        close_delim;

				    var request = gapi.client.request({
				        'path': '/upload/drive/v2/files',
				        'method': 'POST',
				        'params': {'uploadType': 'multipart'},
				        'headers': {
				          'Content-Type': 'multipart/mixed; boundary="' + boundary + '"'
				        },
				        'body': multipartRequestBody});
				    request.execute(callback);
				}
			}


			$scope.authRenameFolder = function(folderID, newName) {
   			try {
   			    gapi.auth.authorize(
   			        {'client_id': CLIENT_ID, 
   			        'scope': SCOPES, 
   			        'cookie_policy': 'single_host_origin',
   			        'authuser': -1,
   			        'immediate': true},
   			        function(authResult) {
   			        	if (authResult && !authResult.error) {
   				       		// authorization granted
   				       		$scope.renameFolder(folderID, newName);
   						}
   						else {
   							// try manual authorization
   							gapi.auth.authorize(
   							    {'client_id': CLIENT_ID, 
   							     'scope': SCOPES, 
   							     'cookie_policy': 'single_host_origin',
   				   			     'immediate': false},
   				   			    function(authResult) {
   				   			       	if (authResult && !authResult.error) {
   				   			       		// authorization granted
   				   			       		$scope.renameFolder(folderID, newName);
   				   					}
   				   					else {
   						    			alert("Authorization failed !")
   						    		}	
		     					});
   		     			}	
		        	});
   				}
   				catch (e) { 
   				    alert(e.message); 
   				}



			}
			/**
			 * Rename a file.
			 *
			 * @param {String} fileId <span style="font-size: 13px; ">ID of the file to rename.</span><br> * @param {String} newTitle New title for the file.
			 */
			$scope.renameFolder = function(fileId, newTitle) {
			  	var body = {'title': newTitle};
			  	var request = gapi.client.drive.files.patch({
			    	'fileId': fileId,
			    	'resource': body
			  	});
			  	request.execute(function(resp) {
			    	console.log('New Title: ' + resp.title);
			    	$scope.close();

			  	});
			}			
	        
	        $scope.close = function() {
	            $scope.inProgress = false;
                alert("Order ID: "+$scope.orderID+" updated successfully");
				window.close();		        	
	        }
})

.directive('progressBar', function() {		// This code is needed to support ie
  return {
    restrict: 'A',
    link: function(scope, element, attrs) {
      var watchFor = attrs.progressBarWatch;
      
      // update now
      var val = scope[watchFor];
      element.attr('aria-valuenow', val)
        .css('width', val+"%");
      
      // watch for the value
      scope.$watch(watchFor, function(val) {
        element.attr('aria-valuenow', val)
          .css('width', val+"%");
      })
    }
  }
})

.directive('fileOnChange', function() {
  return {
    restrict: 'A',
    link: function (scope, element, attrs) {
      var onChangeHandler = scope.$eval(attrs.fileOnChange);
      element.bind('change', onChangeHandler);
    }
  };
})


var mapIconsPath = "http://maps.google.com/mapfiles/";

var mapIcons = [
	'marker_greenA.png',
	'marker_greenB.png',
	'marker_greenC.png',
	'marker_greenD.png',
	'marker_greenE.png',
	'marker_greenF.png',
	'marker_greenG.png',
	'marker_greenH.png',
	'marker_greenI.png',
	'marker_greenJ.png',
	'marker_greenK.png',
	'marker_greenL.png',
	'marker_greenM.png',
	'marker_greenN.png',
	'marker_greenO.png',
	'marker_greenP.png',
	'marker_greenQ.png',
	'marker_greenR.png',
	'marker_greenS.png',
	'marker_greenT.png',
	'marker_greenU.png',
	'marker_greenV.png',
	'marker_greenW.png',
	'marker_greenX.png',
	'marker_greenY.png',
	'marker_greenZ.png'
];