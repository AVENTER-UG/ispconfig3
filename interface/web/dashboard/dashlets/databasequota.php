<?php

class dashlet_databasequota {

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
		$tpl->newTemplate("dashlets/templates/databasequota.htm");

		$wb = array();
		$lng_file = 'lib/lang/'.$_SESSION['s']['language'].'_dashlet_databasequota.lng';
		if(is_file($lng_file)) include $lng_file;
		$tpl->setVar($wb);

		$databases = $app->quota_lib->get_databasequota_data($limit_to_client_id);
		//print_r($databases);

		$total_used = 0;
		if(is_array($databases) && !empty($databases)){
			foreach ($databases as &$db) {
				$db['used'] = $app->functions->formatBytes($db['used_raw'], 0);
				$db['database_quota'] = $app->functions->formatBytesOrUnlimited($db['database_quota_raw'], 0);

				$total_used += $db['used_raw'];
			}
			$databases = $app->functions->htmlentities($databases);
			$tpl->setloop('databasequota', $databases);
			$tpl->setVar('total_used', $app->functions->formatBytes($total_used, 0));

			return $tpl->grab();
		}
	}
}
