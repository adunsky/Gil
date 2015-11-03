 
<?php

include_once "google-api-php-client-master/examples/templates/base.php";
//session_start();

require_once "google-api-php-client-master/autoload.php";

require_once 'class.phpmailer.php';

require_once "mydb.php";


function initGmail($sender) {
	global $appName, $globalDBName;

	$customer = getClient($globalDBName, 1);	// always use the first client

	$clientid = $customer["clientID"];
	$clientmail = $customer["clientMail"];
	$clientkeypath = $customer["clientKeyPath"];

	for ($i=0; $i<5; $i++) {	// retry to initialize 5 times before failing
		try {
			$client = new Google_Client();
			$client->setApplicationName($appName);
			$client->setClientId($clientid);
			$service = new Google_Service_Gmail($client);

			/************************************************
			  If we have an access token, we can carry on.
			  Otherwise, we'll get one with the help of an
			  assertion credential. In other examples the list
			  of scopes was managed by the Client, but here
			  we have to list them manually. We also supply
			  the service account
			 ************************************************/
			
			if (isset($_SESSION[$clientid])) {
			  syslog (LOG_INFO, "Got token from session");	
			  $client->setAccessToken($_SESSION[$clientid]);
			}

			$key = file_get_contents($clientkeypath);
			$cred = new Google_Auth_AssertionCredentials(
			    $clientmail,
			    array('https://www.googleapis.com/auth/gmail.send','https://www.googleapis.com/auth/gmail.compose'),
			    $key
			);
			$cred->sub = $sender;
			$client->setAssertionCredentials($cred);
			
			
			if ($client->getAuth()->isAccessTokenExpired()) {
			  $client->getAuth()->refreshTokenWithAssertion($cred);
			}
				   
			if ($client->getAccessToken()) {
				  	$_SESSION[$clientid] = $client->getAccessToken();
				  	return $service;
			} 
			else {
					syslog (LOG_ERR, "could not access gmail");
			}  
		}
		catch(Exception $e) {
			syslog(LOG_ERR, "Exception: " .$e->getMessage() );
		}			
	}
	return null;
}


function sendMail($to, $fromName, $fromEmail, $subject, $message) {
	$sender = 'admin@googmesh.com';
	$service = initGmail($sender);
	if (!$service)
		syslog(LOG_ERR, "no Gmail service");
	else try {
		$mail = new PHPMailer();
		$mail->CharSet = "UTF-8";
		//$mail->Encoding = 'base64';
		$subject = $subject;
		//$msg = "hey there!";
		//$from = "myemail@gmail.com";
		// $fname = $from;
		$mail->From = $sender;
		$mail->FromName = $fromName;

		$emailList = explode(",", $to);
		foreach ($emailList as $email) {
			syslog(LOG_INFO, 'Emailing to: '.$email);
			// Remove all illegal characters from email
			$email = filter_var($email, FILTER_SANITIZE_EMAIL);
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			  	// it is a valid email address");
			  	$mail->AddAddress($email);
			  	$mail->AddReplyTo($fromEmail, $fromName);
			}
			else
				syslog(LOG_ERR, "Ignoring invalid email address: ".$email);
		}
		$mail->Subject = $subject;
		$message += "\nSent by GoogMesh";
		$mail->Body = $message;
		$mail->preSend();
		$mime = $mail->getSentMIMEMessage();
		syslog(LOG_INFO, "email message before encoding: ".$mime);
		$m = new Google_Service_Gmail_Message();
		$data = base64_encode($mime);
		$data = str_replace(array('+','/','='),array('-','_',''),$data); // url safe
		$m->setRaw($data);
		//syslog(LOG_INFO, "email message: ".$data);
		//var_dump($m);
		$service->users_messages->send($sender, $m);
	}
	catch(Exception $e) {
		syslog(LOG_ERR, "Exception: " .$e->getMessage() );
	}	
}

?>