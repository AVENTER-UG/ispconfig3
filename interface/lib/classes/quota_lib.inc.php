<?php

class quota_lib {
	public function get_quota_data($clientid = null, $readable = true) {
		global $app;

		$tmp_rec =  $app->db->queryAllRecords("SELECT data from monitor_data WHERE type = 'harddisk_quota' ORDER BY created DESC");
		$monitor_data = array();
		if(is_array($tmp_rec)) {
			foreach ($tmp_rec as $tmp_mon) {
				$monitor_data = array_merge_recursive($monitor_data, unserialize($app->db->unquote($tmp_mon['data'])));
			}
		}
		//print_r($monitor_data);

		// select all websites or websites belonging to client
		$q = "SELECT * FROM web_domain WHERE type = 'vhost' AND ";
		$q .= $app->tform->getAuthSQL('r', '', '', $app->functions->clientid_to_groups_list($clientid));
		$q .= " ORDER BY domain";
		$sites = $app->db->queryAllRecords($q, $clientid);

		//print_r($sites);
		if(is_array($sites) && !empty($sites)){
			for($i=0;$i<sizeof($sites);$i++){
				$username = $sites[$i]['system_user'];
				$sites[$i]['used'] = $monitor_data['user'][$username]['used'];
				$sites[$i]['soft'] = $monitor_data['user'][$username]['soft'];
				$sites[$i]['hard'] = $monitor_data['user'][$username]['hard'];
				$sites[$i]['files'] = $monitor_data['user'][$username]['files'];

				if (!is_numeric($sites[$i]['used'])){
					if ($sites[$i]['used'][0] > $sites[$i]['used'][1]){
						$sites[$i]['used'] = $sites[$i]['used'][0];
					} else {
						$sites[$i]['used'] = $sites[$i]['used'][1];
					}
				}
				if (!is_numeric($sites[$i]['soft'])) $sites[$i]['soft']=$sites[$i]['soft'][1];
				if (!is_numeric($sites[$i]['hard'])) $sites[$i]['hard']=$sites[$i]['hard'][1];
				if (!is_numeric($sites[$i]['files'])) $sites[$i]['files']=$sites[$i]['files'][1];

                               // Convert from kb to bytes, and use -1 for instead of 0 for Unlimited.
				$sites[$i]['used_raw'] = $sites[$i]['used'] * 1024;
                               $sites[$i]['soft_raw'] = ($sites[$i]['soft'] > 0) ? $sites[$i]['soft'] * 1024 : -1;
                               $sites[$i]['hard_raw'] = ($sites[$i]['hard'] > 0) ? $sites[$i]['hard'] * 1024 : -1;
				$sites[$i]['files_raw'] = $sites[$i]['files'];
				$sites[$i]['used_percentage'] = ($sites[$i]['soft'] > 0 && $sites[$i]['used'] > 0 ? round($sites[$i]['used'] * 100 / $sites[$i]['soft']) : 0);

				if ($readable) {
					// colours
					$sites[$i]['display_colour'] = '#000000';
					if($sites[$i]['soft'] > 0){
						$used_ratio = $sites[$i]['used']/$sites[$i]['soft'];
					} else {
						$used_ratio = 0;
					}
					if($used_ratio >= 0.8) $sites[$i]['display_colour'] = '#fd934f';
					if($used_ratio >= 1) $sites[$i]['display_colour'] = '#cc0000';


					/*
					 if(!strstr($sites[$i]['used'],'M') && !strstr($sites[$i]['used'],'K')) $sites[$i]['used'].= ' B';
					if(!strstr($sites[$i]['soft'],'M') && !strstr($sites[$i]['soft'],'K')) $sites[$i]['soft'].= ' B';
					if(!strstr($sites[$i]['hard'],'M') && !strstr($sites[$i]['hard'],'K')) $sites[$i]['hard'].= ' B';
					*/
				}

			}
		}

		return $sites;
	}

	public function get_trafficquota_data($clientid = null, $lastdays = 0) {
		global $app;

		$traffic_data = array();

		// select vhosts (belonging to client)
		if($clientid != null){
			$sql_where = " AND sys_groupid = (SELECT default_group FROM sys_user WHERE client_id=?)";
		}
		$sites = $app->db->queryAllRecords("SELECT * FROM web_domain WHERE active = 'y' AND (type = 'vhost' OR type = 'vhostsubdomain' OR type = 'vhostalias')".$sql_where, $clientid);

		$hostnames = array();
		$traffic_data = array();

		foreach ($sites as $site) {
			$hostnames[] = $site['domain'];
			$traffic_data[$site['domain']]['domain_id'] = $site['domain_id'];
		}

		// fetch all traffic-data of selected vhosts
		if (!empty($hostnames)) {
			$tmp_year = date('Y');
			$tmp_month = date('m');
			// This Month
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(traffic_bytes) as t FROM web_traffic WHERE YEAR(traffic_date) = ? AND MONTH(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $tmp_month, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['this_month'] = $tmp_rec['t'];
			}
			// This Year
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(traffic_bytes) as t FROM web_traffic WHERE YEAR(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['this_year'] = $tmp_rec['t'];
			}

			$tmp_year = date('Y', mktime(0, 0, 0, date("m")-1, date("d"), date("Y")));
			$tmp_month = date('m', mktime(0, 0, 0, date("m")-1, date("d"), date("Y")));
			// Last Month
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(traffic_bytes) as t FROM web_traffic WHERE YEAR(traffic_date) = ? AND MONTH(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $tmp_month, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['last_month'] = $tmp_rec['t'];
			}

			$tmp_year = date('Y', mktime(0, 0, 0, date("m"), date("d"), date("Y")-1));
			// Last Year
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(traffic_bytes) as t FROM web_traffic WHERE YEAR(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['last_year'] = $tmp_rec['t'];
			}

			if (is_int($lastdays)  && ($lastdays > 0)) {
				// Last xx Days
				$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(traffic_bytes) as t FROM web_traffic WHERE (traffic_date >= DATE_SUB(NOW(), INTERVAL ? DAY)) AND hostname IN ? GROUP BY hostname", $lastdays, $hostnames);
				foreach ($tmp_recs as $tmp_rec) {
					$traffic_data[$tmp_rec['hostname']]['lastdays'] = $tmp_rec['t'];
				}
			}
		}

		return $traffic_data;
	}

	public function get_ftptrafficquota_data($clientid = null, $lastdays = 0) {
		global $app;

		$traffic_data = array();

		// select vhosts (belonging to client)
		if($clientid != null){
			$sql_where = " AND sys_groupid = (SELECT default_group FROM sys_user WHERE client_id=?)";
		}
		$sites = $app->db->queryAllRecords("SELECT * FROM web_domain WHERE active = 'y' AND (type = 'vhost' OR type = 'vhostsubdomain' OR type = 'vhostalias')".$sql_where, $clientid);

		$hostnames = array();
		$traffic_data = array();

		foreach ($sites as $site) {
			$hostnames[] = $site['domain'];
			$traffic_data[$site['domain']]['domain_id'] = $site['domain_id'];
		}

		// fetch all traffic-data of selected vhosts
		if (!empty($hostnames)) {
			$tmp_year = date('Y');
			$tmp_month = date('m');
			// This Month
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(in_bytes) AS ftp_in, SUM(out_bytes) AS ftp_out FROM ftp_traffic WHERE YEAR(traffic_date) = ? AND MONTH(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $tmp_month, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['this_month'] = $tmp_rec['t'];
			}
			// This Year
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(in_bytes) AS ftp_in, SUM(out_bytes) AS ftp_out FROM ftp_traffic WHERE YEAR(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['this_year'] = $tmp_rec['t'];
			}

			$tmp_year = date('Y', mktime(0, 0, 0, date("m")-1, date("d"), date("Y")));
			$tmp_month = date('m', mktime(0, 0, 0, date("m")-1, date("d"), date("Y")));
			// Last Month
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(in_bytes) AS ftp_in, SUM(out_bytes) AS ftp_out FROM ftp_traffic WHERE YEAR(traffic_date) = ? AND MONTH(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $tmp_month, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['last_month'] = $tmp_rec['t'];
			}

			$tmp_year = date('Y', mktime(0, 0, 0, date("m"), date("d"), date("Y")-1));
			// Last Year
			$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(in_bytes) AS ftp_in, SUM(out_bytes) AS ftp_out FROM ftp_traffic WHERE YEAR(traffic_date) = ? AND hostname IN ? GROUP BY hostname", $tmp_year, $hostnames);
			foreach ($tmp_recs as $tmp_rec) {
				$traffic_data[$tmp_rec['hostname']]['last_year'] = $tmp_rec['t'];
			}

			if (is_int($lastdays)  && ($lastdays > 0)) {
				// Last xx Days
				$tmp_recs = $app->db->queryAllRecords("SELECT hostname, SUM(in_bytes) AS ftp_in, SUM(out_bytes) AS ftp_out FROM ftp_traffic WHERE (traffic_date >= DATE_SUB(NOW(), INTERVAL ? DAY)) AND hostname IN ? GROUP BY hostname", $lastdays, $hostnames);
				foreach ($tmp_recs as $tmp_rec) {
					$traffic_data[$tmp_rec['hostname']]['lastdays'] = $tmp_rec['t'];
				}
			}
		}

		return $traffic_data;
	}

       public function get_mailquota_data($clientid = null, $readable = true, $email = null) {
		global $app;

		$tmp_rec =  $app->db->queryAllRecords("SELECT data from monitor_data WHERE type = 'email_quota' ORDER BY created DESC");
		$monitor_data = array();
		if(is_array($tmp_rec)) {
			foreach ($tmp_rec as $tmp_mon) {
				//$monitor_data = array_merge_recursive($monitor_data,unserialize($app->db->unquote($tmp_mon['data'])));
				$tmp_array = unserialize($app->db->unquote($tmp_mon['data']));
				if(is_array($tmp_array)) {
					foreach($tmp_array as $username => $data) {
						if(!$monitor_data[$username]['used']) $monitor_data[$username]['used'] = $data['used'];
					}
				}
			}
		}
		//print_r($monitor_data);

               if ($email !== null && !empty($email)) {
				   if(isset($monitor_data[$email])) {
					   return $monitor_data[$email];
				   } else {
					   return '';
				   }
                       
               }
		// select all email accounts or email accounts belonging to client
		$q = "SELECT * FROM mail_user WHERE";
		$q .= $app->tform->getAuthSQL('r', '', '', $app->functions->clientid_to_groups_list($clientid));
		$q .= " ORDER BY email";
		$emails = $app->db->queryAllRecords($q, $clientid);

		//print_r($emails);
		if(is_array($emails) && !empty($emails)) {
			for($i=0;$i<sizeof($emails);$i++){
				$email = $emails[$i]['email'];

				if (empty($emails[$i]['last_access'])) {
					$emails[$i]['last_access'] = $app->lng('never_accessed_txt');
				}
				else {
					$emails[$i]['last_access'] = date($app->lng('conf_format_dateshort'), $emails[$i]['last_access']);
				}

				$emails[$i]['name'] = $app->functions->htmlentities($emails[$i]['name']);
				$emails[$i]['used'] = isset($monitor_data[$email]['used']) ? $monitor_data[$email]['used'] : array(1 => 0);

				if (!is_numeric($emails[$i]['used'])) $emails[$i]['used']=$emails[$i]['used'][1];

				$emails[$i]['quota_raw'] = $emails[$i]['quota'];
				$emails[$i]['used_raw'] = $emails[$i]['used'];
				$emails[$i]['used_percentage'] = ($emails[$i]['quota'] > 0 && $emails[$i]['used'] > 0 ? round($emails[$i]['used'] * 100 / $emails[$i]['quota']) : 0);


				if ($readable) {
					// colours
					$emails[$i]['display_colour'] = '#000000';
					if($emails[$i]['quota'] > 0){
						$used_ratio = $emails[$i]['used']/$emails[$i]['quota'];
					} else {
						$used_ratio = 0;
					}
					if($used_ratio >= 0.8) $emails[$i]['display_colour'] = '#fd934f';
					if($used_ratio >= 1) $emails[$i]['display_colour'] = '#cc0000';

					if($emails[$i]['quota'] == 0) {
						$emails[$i]['quota'] = -1;
					}
				}
			}
		}

		return $emails;
	}

	public function get_databasequota_data($clientid = null, $readable = true) {
		global $app;

		$tmp_rec =  $app->db->queryAllRecords("SELECT data from monitor_data WHERE type = 'database_size' ORDER BY created DESC");
		$monitor_data = array();
		if(is_array($tmp_rec)) {
			foreach ($tmp_rec as $tmp_mon) {
				$tmp_array = unserialize($app->db->unquote($tmp_mon['data']));
				if(is_array($tmp_array)) {
					foreach($tmp_array as $key => $data) {
						if(!isset($monitor_data[$data['database_name']]['size'])) $monitor_data[$data['database_name']]['size'] = $data['size'];
					}
				}
			}
		}
		//print_r($monitor_data);

		// select all databases belonging to client
		$q = "SELECT * FROM web_database WHERE";
		$q .= $app->tform->getAuthSQL('r', '', '', $app->functions->clientid_to_groups_list($clientid));
		$q .= " ORDER BY database_name";
		$databases = $app->db->queryAllRecords($q);

		//print_r($databases);
		if(is_array($databases) && !empty($databases)){
			for($i=0;$i<sizeof($databases);$i++){
				$databasename = $databases[$i]['database_name'];

				$size = isset($monitor_data[$databasename]['size']) ? $monitor_data[$databasename]['size'] : 0;

				$databases[$i]['database_quota_raw'] = ($databases[$i]['database_quota'] == -1) ? -1 : $databases[$i]['database_quota'] * 1000 * 1000;
				$databases[$i]['used_raw'] = $size; // / 1024 / 1024; //* quota is stored as MB - calculated bytes
				$databases[$i]['used_percentage'] = (($databases[$i]['database_quota'] > 0) && ($size > 0)) ? round($databases[$i]['used_raw'] * 100 / $databases[$i]['database_quota_raw']) : 0;

				if ($readable) {
					// colours
					$databases[$i]['display_colour'] = '#000000';
					if($databases[$i]['database_quota'] > 0){
						$used_ratio = $databases[$i]['used'] / $databases[$i]['database_quota'];
					} else {
						$used_ratio = 0;
					}
					if($used_ratio >= 0.8) $databases[$i]['display_colour'] = '#fd934f';
					if($used_ratio >= 1) $databases[$i]['display_colour'] = '#cc0000';



				}
			}
		}

		return $databases;
	}

}
