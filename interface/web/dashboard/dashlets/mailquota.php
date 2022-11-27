<?php

class dashlet_mailquota {

	function show($limit_to_client_id = null) {
		global $app;

		//* Loading Template
		$app->uses('tpl,quota_lib');

		$tpl = new tpl;
		$tpl->newTemplate("dashlets/templates/mailquota.htm");

		$wb = array();
		$lng_file = 'lib/lang/'.$_SESSION['s']['language'].'_dashlet_mailquota.lng';
		if(is_file($lng_file)) include $lng_file;
		$tpl->setVar($wb);

		if ($_SESSION["s"]["user"]["typ"] != 'admin') {
			$client_id = $_SESSION['s']['user']['client_id'];
		} else {
			$client_id = $limit_to_client_id;
		}

		$emails = $app->quota_lib->get_mailquota_data($client_id);
		//print_r($emails);

		$has_mailquota = false;
		if(is_array($emails) && !empty($emails)){
			foreach($emails as &$email) {
				$email['email'] = $app->functions->idn_decode($email['email']);
			}
			unset($email);
			// email username is quoted in quota.lib already, so no htmlentities here to prevent double encoding
			//$emails = $app->functions->htmlentities($emails);
			$tpl->setloop('mailquota', $emails);
			$has_mailquota = isset($emails[0]['used']);
		}
		$tpl->setVar('has_mailquota', $has_mailquota);

		return $tpl->grab();
	}

}








?>
