<?php

require 'soap_config.php';


$client = new SoapClient(null, array('location' => $soap_location,
		'uri'      => $soap_uri,
		'trace' => 1,
		'exceptions' => 1));


try {
	if($session_id = $client->login($username, $password)) {
		echo 'Logged successfull. Session ID:'.$session_id.'<br />';
	}

	//* Set the function parameters.
	$domain_id = 2;
	$domain_name = 'example.com';

	// Lookup by ID.
	$domain_record = $client->sites_web_domain_get($session_id, $domain_id);

	// Lookup by name.
	$domain_record = $client->sites_web_domain_get($session_id, array('domain' => $domain_name));

	print_r($domain_record);

	if($client->logout($session_id)) {
		echo 'Logged out.<br />';
	}


} catch (SoapFault $e) {
	echo $client->__getLastResponse();
	die('SOAP Error: '.$e->getMessage());
}

?>
