<?php
/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
include_once "templates/base.php";
session_start();

require_once realpath(dirname(__FILE__) . '/../autoload.php');

/************************************************
  ATTENTION: Fill in these values! Make sure
  the redirect URI is to this page, e.g:
  http://localhost:8080/user-example.php
 ************************************************/
 $client_id = '949470023217-22oljsn3tid1qm113k1otc6qpo8qaidl.apps.googleusercontent.com';
 $client_secret = 'TYC7ZjKWZoMQup2ZvsLYrTNp';
 $redirect_uri = 'http://localhost/gil/google-api-php-client-master/examples/multi-api.php';

/************************************************
  Make an API request on behalf of a user. In
  this case we need to have a valid OAuth 2.0
  token for the user, so we need to send them
  through a login flow. To do this we need some
  information from our API console project.
 ************************************************/
$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);

$client->addScope("https://www.googleapis.com/auth/calendar");
//$client->addScope("https://www.googleapis.com/auth/youtube");

/************************************************
  We are going to create both YouTube and Drive
  services, and query both.
 ************************************************/
$service = new Google_Service_Calendar($client);
//$dr_service = new Google_Service_Drive($client);


/************************************************
  Boilerplate auth management - see
  user-example.php for details.
 ************************************************/
if (isset($_REQUEST['logout'])) {
  unset($_SESSION['access_token']);
}
if (isset($_GET['code'])) {
  $client->authenticate($_GET['code']);
  $_SESSION['access_token'] = $client->getAccessToken();
  $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
  $client->setAccessToken($_SESSION['access_token']);
} else {
  $authUrl = $client->createAuthUrl();
}

    
if ($client->getAccessToken()) {
  $_SESSION['access_token'] = $client->getAccessToken();

	$calendar = $service->calendars->get('primary');

	echo $calendar->getSummary();

/*
	$event = new Google_Service_Calendar_Event();
	$event->setSummary('Appointment');
	$event->setLocation('Somewhere');
	$start = new Google_Service_Calendar_EventDateTime();
	$start->setDateTime('2015-02-13T10:00:00.000-07:00');
	$start->setTimeZone('America/Los_Angeles');
	$event->setStart($start);
	$end = new Google_Service_Calendar_EventDateTime();
	$end->setDateTime('2015-02-13T10:25:00.000-07:00');
	$end->setTimeZone('America/Los_Angeles');
	$event->setEnd($end);
	$event->setRecurrence(array('RRULE:FREQ=WEEKLY;UNTIL=20110701T170000Z'));
	$attendee1 = new Google_Service_Calendar_EventAttendee();
	$attendee1->setEmail('attendeeEmail');
	// ...
	$attendees = array($attendee1,
	                   // ...
	                   );
	$event->attendees = $attendees;
		
	$recurringEvent = $service->events->insert('primary', $event);
		
	echo $recurringEvent->getId();
	
*/	
}
else {
	echo "could not access calendar";
}


?>
