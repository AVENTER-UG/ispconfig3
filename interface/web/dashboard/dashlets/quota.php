<?php

class dashlet_quota {

	function show($limit_to_client_id = null) {
		global $app;

		//* Loading Template
		$app->uses('tpl,quota_lib');
		if (!$app->auth->verify_module_permissions('sites')) {
				return;
		}

		$modules = explode(',', $_SESSION['s']['user']['modules']);
		if(!in_array('sites', $modules)) {
			return '';
		}

		$tpl = new tpl;
		$tpl->newTemplate("dashlets/templates/quota.htm");

		$wb = array();
		$lng_file = 'lib/lang/'.$_SESSION['s']['language'].'_dashlet_quota.lng';
		if(is_file($lng_file)) include $lng_file;
		$tpl->setVar($wb);

		if ($_SESSION["s"]["user"]["typ"] != 'admin') {
			$client_id = $_SESSION['s']['user']['client_id'];
		} else {
			$client_id = $limit_to_client_id;
		}

		$sites = $app->quota_lib->get_quota_data($client_id);
		//print_r($sites);

		$has_quota = false;
		if(is_array($sites) && !empty($sites)){
			foreach($sites as &$site) {
				$site['domain'] = $app->functions->idn_decode($site['domain']);
				$site['progressbar'] = $site['hd_quota'];
				$total_used += $site['used_raw'] * 1000;
			}
			unset($site);

			$sites = $app->functions->htmlentities($sites);
			$tpl->setloop('quota', $sites);
			$has_quota = isset($sites[0]['used']);
		}
		$tpl->setVar('has_quota', $has_quota);
		$tpl->setVar('total_used', $app->functions->formatBytes($total_used, 0));

		return $tpl->grab();


	}

}








?>
