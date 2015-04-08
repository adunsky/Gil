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



orderApp.controller('orderCtrl', function($scope, $http, $timeout, myService){
			$scope.order = myService.getOrder();
			$scope.inProgress = false;
			
      	$scope.getOrder = function () {
			   var parameters = location.search.substring(1).split("&");
			   var eventID = null;
			
			   var temp = parameters[0].split("=");
			   if (temp != "") {
			   	eventID = unescape(temp[1]);
			   }
			   else {
			   	eventID = 0;
			   }

	     		$http.get("getOrder.php", { params: { eventID: eventID }})
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
	            		console.log("did not receive a valid Json: " + e);
	            		myService.setOrder(null);
	        		 } 
				}); 
			};
			
			
      	$scope.getFormFields = function (form) {
				if (!$scope.forms) {
		     		$http.get("getForms.php")
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
		            		console.log("did not receive a valid Json: " + e);
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
			
				
			$scope.setFormValues = function()	{
					if ($scope.order) 
						for(var i=0; $scope.form && $scope.form.fields[i]; i++) {
		      			var fieldIndex = $scope.form.fields[i].fieldIndex-2;
		      			$scope.form.fields[i].value = $scope.order[fieldIndex].value;	
		      		}    		
			}
			
			
			$scope.openDateTimeCalendar = function(e, field) {
			    e.preventDefault();
			    e.stopPropagation();
			
			    field.dateTimeCalendarisOpen = true;
			};
      
	      $scope.updateOrder = function () {
	      		document.body.style.cursor = 'wait';
	      		$scope.progress = 0;
					$scope.inProgress = true;
	      		setProgress(); 
	      		$scope.updateValues(); 
	      		// get the order ID and send to PHP
					$updatedOrder = {};
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
						 if (!isNaN(data)) 	                
	                	$scope.orderID = data; // PHP returned a valid ID number
	                console.log($scope.message);
	                $scope.inProgress = false;
	                alert("Order ID: "+$scope.orderID+" updated successfully");
  						 window.close();
	          
	            });
	            request.error(function (data, status) {
	                $scope.message = "From PHP file : "+data;
	                $scope.inProgress = false;
	                alert($scope.message);
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
	            var content = angular.toJson($scope.order);
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
						$scope.order = angular.fromJson(data);
		            $scope.setFormValues();
		            myService.setOrder($scope.order);						
						document.body.style.cursor = 'default';
		            $scope.inProgress = false;	
							          
	            });
	            request.error(function (data, status) {
	                $scope.message = "From PHP file : "+data;
	                $scope.inProgress = false;
	                alert($scope.message);
	            });

			};
			
         
	        
});


