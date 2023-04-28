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
		if ($_SESSION["s"]["user"]["typ"] != 'admin') {
			$client_id = $_SESSION['s']['user']['client_id'];
		} else {
			$client_id = $limit_to_client_id;
		}

		$databases = $app->quota_lib->get_databasequota_data($client_id);
		//print_r($databases);

		$has_databasequota = false;
		if(is_array($databases) && !empty($databases)){
			$databases = $app->functions->htmlentities($databases);
			$tpl->setloop('databasequota', $databases);
			$has_databasequota = isset($databases[0]['used']);
		}
		$tpl->setVar('has_databasequota', $has_databasequota);
		
		return $tpl->grab();
	}

}








?>
