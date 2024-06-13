<?php

class dashlet_mailquota {

	function show() {
		global $app;

		//* Loading Template
		$app->uses('tpl,quota_lib');

		$tpl = new tpl;
		$tpl->newTemplate("dashlets/templates/mailquota.htm");

		$wb = array();
		$lng_file = 'lib/lang/'.$_SESSION['s']['language'].'_dashlet_mailquota.lng';
		if(is_file($lng_file)) include $lng_file;
               $wb['last_accessed_txt'] = $app->lng('last_accessed_txt');
		$tpl->setVar($wb);

		$app->uses('getconf');
		$mail_config = $app->getconf->get_global_config('mail');
		$tpl->setVar('mailbox_show_last_access', $mail_config['mailbox_show_last_access']);

		$emails = $app->quota_lib->get_mailquota_data( ($_SESSION["s"]["user"]["typ"] != 'admin') ? $_SESSION['s']['user']['client_id'] : null);

		$has_mailquota = false;
		if(is_array($emails) && !empty($emails)){
			foreach($emails as &$email) {
				$email['email'] = $app->functions->idn_decode($email['email']);
			}
			unset($email);
			$tpl->setloop('mailquota', $emails);
			$has_mailquota = isset($emails[0]['used']);
		}
		$tpl->setVar('has_mailquota', $has_mailquota);

		return $tpl->grab();
	}
}
