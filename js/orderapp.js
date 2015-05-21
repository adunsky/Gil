var orderApp = angular.module('orderApp',['ngRoute', 'ui.bootstrap', 'ui.bootstrap.datetimepicker']);

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

          .when('/updateOrder',{
                templateUrl: 'updateOrder.html'
          });

});



orderApp.controller('orderCtrl', function($scope, $http, $timeout, $sce, $location, myService){
			$scope.order = myService.getOrder();
			$scope.inProgress = false;
			
      	$scope.getOrder = function () {
     		
			   var eventID;
				
				var argv = $location.search();      		
    			if (argv.id)
    				eventID = argv.id;
    			if (argv.db)
    				$scope.dbName = argv.db;
    			if (argv.user)
     				$scope.user = argv.user;
     			else 
     				$scope.user = ""; 	// The user is verified only for new orders			

	     		$http.get("getOrder.php", { params: { eventID: eventID, db: $scope.dbName, user: $scope.user } })
	     		.success(function(data) {
	             $scope.message = "From PHP file : "+data;
	             console.log($scope.message);
	      	    try {
	   	        		$updatedOrder = angular.fromJson(data);
	   	        		if ($updatedOrder) {
	   	        			$scope.orderID = $updatedOrder.orderID;
	   	        			$scope.order = $updatedOrder.order;
	             			myService.setOrder($scope.order);
	             			$scope.getFormFields($updatedOrder.formID)
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
		             $scope.message = "From PHP file : "+data;
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
			    var minutes = (Math.abs(offset) - hours)*10;

			    if (offset == Math.abs(offset)) // positive
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
		      			if ($scope.form.fields[i].type == 'EmbedHyperlink')
		      				$scope.form.fields[i].value = $sce.trustAsResourceUrl($scope.order[fieldIndex].value);
		      			else
		      				$scope.form.fields[i].value = $scope.order[fieldIndex].value;
		    				if ($scope.form.fields[i].type == 'LIST') {
								// add the current value to the list if it is not there
								if ($scope.form.fields[i].listValues.indexOf($scope.form.fields[i].value) == -1)
									$scope.form.fields[i].listValues.push($scope.form.fields[i].value);	    				
		    					// add an empty string to the list if not already there
								if ($scope.form.fields[i].value != "")		    					
		    						$scope.form.fields[i].listValues.push("");
		    				}
		    				if ($scope.form.fields[i].type == 'DATETIME' && $scope.form.fields[i].fieldType == "Edit") {
		    					// the control expects format yyyy-mm-ddThh:mm
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
			    showMeridian: false
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
	      		
	            var content = angular.toJson($updatedOrder);
	            var request = $http({
	                    method: "post",
	                    url: "updateOrder.php",
	                    data: content,
	                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
	            });
	                /* Check whether the HTTP Request is Successfull or not. */
	            request.success(function (data) {
	                $scope.message = "From PHP file : "+data;
	                console.log($scope.message);
						 if (!isNaN(data)) {	                
	                	$scope.orderID = data; // PHP returned a valid ID number
	                	$scope.inProgress = false;
	                	alert("Order ID: "+$scope.orderID+" updated successfully");
  						 	window.close();	                	
	                }
	                else {
	                	$scope.orderID = data; // PHP returned a valid ID number
	                	$scope.inProgress = false;
	                	alert("Error: "+$scope.message);
	                }	
	            });
	            request.error(function (data, status) {
	                $scope.message = "From PHP file : "+data;
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
	               $scope.message = "From PHP file : "+data;
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
	                $scope.message = "From PHP file : "+data;
	                $scope.inProgress = false;
						 document.body.style.cursor = 'default';	                
	                alert($scope.message);
	            });

			};
			
         
	        
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
});
