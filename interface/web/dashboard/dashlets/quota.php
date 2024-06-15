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

		$sites = $app->quota_lib->get_quota_data($limit_to_client_id);

		$has_quota = false;
		if(is_array($sites) && !empty($sites)){
			foreach($sites as &$site) {
				$site['domain'] = $app->functions->idn_decode($site['domain']);
				$site['progressbar'] = $site['hd_quota'];
				$site['used'] = $app->functions->formatBytes($site['used_raw'], 0);
				$site['hard'] = $app->functions->formatBytesOrUnlimited($site['hard_raw'], 0);
				$site['soft'] = $app->functions->formatBytesOrUnlimited($site['soft_raw'], 0);
				$total_used += $site['used_raw'];
			}
			unset($site);

			$sites = $app->functions->htmlentities($sites);
			$tpl->setloop('quota', $sites);
			$has_quota = isset($sites[0]['used']);

			$tpl->setVar('has_quota', $has_quota);
			$tpl->setVar('total_used', $app->functions->formatBytes($total_used, 0));

			return $tpl->grab();
		}
	}
}
