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
               $wb['last_accessed_txt'] = $app->lng('last_accessed_txt');
		$tpl->setVar($wb);

		$app->uses('getconf');
		$mail_config = $app->getconf->get_global_config('mail');
		$tpl->setVar('mailbox_show_last_access', $mail_config['mailbox_show_last_access']);

		$emails = $app->quota_lib->get_mailquota_data($limit_to_client_id);

		$has_mailquota = false;
		$total_used = 0;
		if(is_array($emails) && !empty($emails)){
			foreach($emails as &$email) {
				$email['email'] = $app->functions->idn_decode($email['email']);
				$email['used'] = $app->functions->formatBytes($email['used_raw'], 0);
				// Mail is the exception with 0 == unlimited, instead of -1
				if ($email['quota_raw'] == 0) {
					$email['quota_raw'] = -1;
				}

				$email['quota'] = $app->functions->formatBytesOrUnlimited($email['quota_raw'], 0);
				$total_used += $email['used_raw'];
			}
			unset($email);
			$tpl->setloop('mailquota', $emails);
			$has_mailquota = isset($emails[0]['used']);

			$tpl->setVar('has_mailquota', $has_mailquota);
			$tpl->setVar('total_used', $app->functions->formatBytes($total_used, 0));

			return $tpl->grab();
		}
	}
}
