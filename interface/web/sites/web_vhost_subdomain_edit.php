<?php
/*
Copyright (c) 2007 - 2009, Till Brehm, projektfarm Gmbh
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/


/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/web_vhost_subdomain.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

//* Check permissions for module
$app->auth->check_module_permissions('sites');

// Loading classes
$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

class page_action extends tform_actions {

	//* Returna a "3/2/1" path hash from a numeric id '123'
	function id_hash($id,$levels) {
		$hash = "" . $id % 10 ;
		$id /= 10 ;
		$levels -- ;
		while ( $levels > 0 ) {
			$hash .= "/" . $id % 10 ;
			$id /= 10 ;
			$levels-- ;
		}
		return $hash;
	}
	
	function onShowNew() {
		global $app, $conf;

		// we will check only users, not admins
		if($_SESSION["s"]["user"]["typ"] == 'user') {
			if(!$app->tform->checkClientLimit('limit_web_subdomain',"(type = 'subdomain' OR type = 'vhostsubdomain')")) {
				$app->error($app->tform->wordbook["limit_web_subdomain_txt"]);
			}
			if(!$app->tform->checkResellerLimit('limit_web_subdomain',"(type = 'subdomain' OR type = 'vhostsubdomain')")) {
				$app->error('Reseller: '.$app->tform->wordbook["limit_web_subdomain_txt"]);
			}
		}
		parent::onShowNew();
	}

	function onShowEnd() {
		global $app, $conf;
		
		$app->uses('ini_parser,getconf');

        $read_limits = array('limit_cgi', 'limit_ssi', 'limit_perl', 'limit_ruby', 'limit_python', 'force_suexec', 'limit_hterror', 'limit_wildcard', 'limit_ssl');
		
        $parent_domain = $app->db->queryOneRecord("select * FROM web_domain WHERE domain_id = ".intval(@$this->dataRecord["parent_domain_id"]));
        
		//* Client: If the logged in user is not admin and has no sub clients (no reseller)
		if($_SESSION["s"]["user"]["typ"] != 'admin' && !$app->auth->has_clients($_SESSION['s']['user']['userid'])) {

			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT client.limit_web_subdomain, client.default_webserver, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			
			//* Get global web config
			$web_config = $app->getconf->get_server_config($parent_domain['server_id'], 'web');

			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';
			if($this->dataRecord['php'] == 'php-fpm'){
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".$parent_domain['server_id']." AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")");
			}
			if($this->dataRecord['php'] == 'fast-cgi'){
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".$parent_domain['server_id']." AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")");
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version",$php_select);
			unset($php_records);

            // add limits to template to be able to hide settings
            foreach($read_limits as $limit) $app->tpl->setVar($limit, $client[$limit]);
            
            
			//* Reseller: If the logged in user is not admin and has sub clients (is a reseller)
		} elseif ($_SESSION["s"]["user"]["typ"] != 'admin' && $app->auth->has_clients($_SESSION['s']['user']['userid'])) {

			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT client.client_id, client.limit_web_subdomain, client.default_webserver, client.contact_name, CONCAT(client.company_name,' :: ',client.contact_name) as contactname, sys_group.name, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			
			//* Get global web config
			$web_config = $app->getconf->get_server_config($parent_domain['server_id'], 'web');
			
			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';
			if($this->dataRecord['php'] == 'php-fpm'){
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".$parent_domain['server_id']." AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")");
			}
			if($this->dataRecord['php'] == 'fast-cgi') {
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".$parent_domain['server_id']." AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")");
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version",$php_select);
			unset($php_records);
            
            // add limits to template to be able to hide settings
            foreach($read_limits as $limit) $app->tpl->setVar($limit, $client[$limit]);
            
            
			//* Admin: If the logged in user is admin
		} else {

			//* get global web config
			$web_config = $app->getconf->get_server_config($parent_domain['server_id'], 'web');
			
			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';
			if($this->dataRecord['php'] == 'php-fpm'){
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = " . $parent_domain['server_id']);
			}
			if($this->dataRecord['php'] == 'fast-cgi') {
				$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = " . $parent_domain['server_id']);
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version",$php_select);
			unset($php_records);

            foreach($read_limits as $limit) $app->tpl->setVar($limit, ($limit == 'force_suexec' ? 'n' : 'y'));
		}

		$ssl_domain_select = '';
		$tmp = $app->db->queryOneRecord("SELECT domain FROM web_domain WHERE domain_id = ".$this->id);
		$ssl_domains = array($tmp["domain"],'www.'.$tmp["domain"]);
		if(is_array($ssl_domains)) {
			foreach( $ssl_domains as $ssl_domain) {
				$selected = ($ssl_domain == $this->dataRecord['ssl_domain'])?'SELECTED':'';
				$ssl_domain_select .= "<option value='$ssl_domain' $selected>$ssl_domain</option>\r\n";
			}
		}
        
        if($this->id > 0) {
            $app->tpl->setVar('fixed_folder', 'y');
            $app->tpl->setVar('server_id_value', $parent_domain['server_id']);
        } else {
            $app->tpl->setVar('fixed_folder', 'n');
            $app->tpl->setVar('server_id_value', $parent_domain['server_id']);
        }
        
		$app->tpl->setVar("ssl_domain",$ssl_domain_select);
		unset($ssl_domain_select);
		unset($ssl_domains);
		unset($ssl_domain);

		$tmp_txt = ($this->dataRecord['traffic_quota_lock'] == 'y')?'<b>('.$app->tform->lng('traffic_quota_exceeded_txt').')</b>':'';
		$app->tpl->setVar("traffic_quota_exceeded_txt", $tmp_txt);


		$app->uses('ini_parser,getconf');
		$settings = $app->getconf->get_global_config('domains');
		if ($settings['use_domain_module'] == 'y') {
			/*
			 * The domain-module is in use.
			*/
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			/*
			 * The admin can select ALL domains, the user only the domains assigned to him
			 */
			$sql = "SELECT domain_id, domain FROM domain ";
			if ($_SESSION["s"]["user"]["typ"] != 'admin') {
				$sql .= "WHERE sys_groupid =" . $client_group_id;
			}
			$sql .= " ORDER BY domain";
			$domains = $app->db->queryAllRecords($sql);
			$domain_select = '';
            $selected_domain = '';
			if(is_array($domains) && sizeof($domains) > 0) {
				/* We have domains in the list, so create the drop-down-list */
				foreach( $domains as $domain) {
					$domain_select .= "<option value=" . $domain['domain_id'] ;
					if ('.' . $domain['domain'] == substr($this->dataRecord["domain"], -strlen($domain['domain']) - 1)) {
						$domain_select .= " selected";
                        $selected_domain = $domain['domain'];
					}
					$domain_select .= ">" . $domain['domain'] . "</option>\r\n";
				}
			}
			else {
				/*
				 * We have no domains in the domain-list. This means, we can not add ANY new domain.
				 * To avoid, that the variable "domain_option" is empty and so the user can
				 * free enter a domain, we have to create a empty option!
				*/
				$domain_select .= "<option value=''></option>\r\n";
			}
			$app->tpl->setVar("domain_option",$domain_select);
            $this->dataRecord['domain'] = substr($this->dataRecord["domain"], 0, strlen($this->dataRecord['domain']) - strlen($selected_domain) - 1);
		} else {
        
            // remove the parent domain part of the domain name before we show it in the text field.
            $this->dataRecord["domain"] = str_replace('.'.$parent_domain["domain"],'',$this->dataRecord["domain"]);
        }
        $app->tpl->setVar("domain",$this->dataRecord["domain"]);

		parent::onShowEnd();
	}
    
	function onSubmit() {
		global $app, $conf;

		// Get the record of the parent domain
		$parent_domain = $app->db->queryOneRecord("select * FROM web_domain WHERE domain_id = ".intval(@$this->dataRecord["parent_domain_id"]));

		// Set a few fixed values
		$this->dataRecord["type"] = 'vhostsubdomain';
		$this->dataRecord["server_id"] = $parent_domain["server_id"];
		$this->dataRecord["ip_address"] = $parent_domain["ip_address"];
		$this->dataRecord["ipv6_address"] = $parent_domain["ipv6_address"];
		$this->dataRecord["client_group_id"] = $parent_domain["client_group_id"];
		$this->dataRecord["vhost_type"] = 'name';
		$this->dataRecord["domain"] = $this->dataRecord["domain"].'.'.$parent_domain["domain"];

		$this->parent_domain_record = $parent_domain;
        
        $read_limits = array('limit_cgi', 'limit_ssi', 'limit_perl', 'limit_ruby', 'limit_python', 'force_suexec', 'limit_hterror', 'limit_wildcard', 'limit_ssl');
        
        if($app->tform->getCurrentTab() == 'domain') {
            
            /* check if the domain module is used - and check if the selected domain can be used! */
            $app->uses('ini_parser,getconf');
            $settings = $app->getconf->get_global_config('domains');
            if ($settings['use_domain_module'] == 'y') {
                $client_group_id = intval($_SESSION["s"]["user"]["default_group"]);
                
                $sql = "SELECT domain_id, domain FROM domain WHERE domain_id = " . intval($this->dataRecord['sel_domain']);
                if ($_SESSION["s"]["user"]["typ"] != 'admin') {
                    $sql .= "AND sys_groupid =" . $client_group_id;
                }
                $domain_check = $app->db->queryOneRecord($sql);
                if(!$domain_check) {
                    // invalid domain selected
                    $app->tform->errorMessage .= $app->tform->lng("domain_error_empty")."<br />";
                } else {
                    $this->dataRecord['domain'] = $this->dataRecord['domain'] . '.' . $domain_check['domain'];
                }
            } else {
                $this->dataRecord["domain"] = $this->dataRecord["domain"].'.'.$parent_domain["domain"];
            }
            
            
            $this->dataRecord['web_folder'] = strtolower($this->dataRecord['web_folder']);
            $forbidden_folders = array('', 'cgi-bin', 'web', 'log', 'private', 'ssl', 'tmp', 'webdav');
            if(in_array($this->dataRecord['web_folder'], $forbidden_folders)) {
                $app->tform->errorMessage .= $app->tform->lng("web_folder_invalid_txt")."<br>";
            }
            // check for duplicate folder usage
            $check = $app->db->queryOneRecord("SELECT COUNT(*) as `cnt` FROM `web_domain` WHERE `type` = 'vhostsubdomain' AND `parent_domain_id` = '" . intval($this->dataRecord['parent_domain_id']) . "' AND `web_folder` = '" . $app->db->quote($this->dataRecord['web_folder']) . "' AND `domain_id` != '" . intval($this->id) . "'");
            if($check && $check['cnt'] > 0) {
                $app->tform->errorMessage .= $app->tform->lng("web_folder_unique_txt")."<br>";
            }
        }
        
		if($_SESSION["s"]["user"]["typ"] != 'admin') {
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT limit_traffic_quota, limit_web_subdomain, default_webserver, parent_client_id, limit_web_quota, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
            
            if($client['limit_cgi'] != 'y') $this->dataRecord['cgi'] = '-';
            if($client['limit_ssi'] != 'y') $this->dataRecord['ssi'] = '-';
            if($client['limit_perl'] != 'y') $this->dataRecord['perl'] = '-';
            if($client['limit_ruby'] != 'y') $this->dataRecord['ruby'] = '-';
            if($client['limit_python'] != 'y') $this->dataRecord['python'] = '-';
            if($client['force_suexec'] != 'n') $this->dataRecord['suexec'] = '-';
            if($client['limit_hterror'] != 'y') $this->dataRecord['errordocs'] = '-';
            if($client['limit_wildcard'] != 'y' && $this->dataRecord['subdomain'] == '*') $this->dataRecord['subdomain'] = '-';
            if($client['limit_ssl'] != 'y') $this->dataRecord['ssl'] = '-';
            
			//* Check the website quota of the client
			if(isset($_POST["hd_quota"]) && $client["limit_web_quota"] >= 0) {
				$tmp = $app->db->queryOneRecord("SELECT sum(hd_quota) as webquota FROM web_domain WHERE domain_id != ".intval($this->id)." AND ".$app->tform->getAuthSQL('u'));
				$webquota = $tmp["webquota"];
				$new_web_quota = intval($this->dataRecord["hd_quota"]);
				if(($webquota + $new_web_quota > $client["limit_web_quota"]) || ($new_web_quota < 0 && $client["limit_web_quota"] >= 0)) {
					$max_free_quota = floor($client["limit_web_quota"] - $webquota);
					if($max_free_quota < 0) $max_free_quota = 0;
					$app->tform->errorMessage .= $app->tform->lng("limit_web_quota_free_txt").": ".$max_free_quota." MB<br>";
					// Set the quota field to the max free space
					$this->dataRecord["hd_quota"] = $max_free_quota;
				}
				unset($tmp);
				unset($tmp_quota);
			}

			//* Check the traffic quota of the client
			if(isset($_POST["traffic_quota"]) && $client["limit_traffic_quota"] > 0) {
				$tmp = $app->db->queryOneRecord("SELECT sum(traffic_quota) as trafficquota FROM web_domain WHERE domain_id != ".intval($this->id)." AND ".$app->tform->getAuthSQL('u'));
				$trafficquota = $tmp["trafficquota"];
				$new_traffic_quota = intval($this->dataRecord["traffic_quota"]);
				if(($trafficquota + $new_traffic_quota > $client["limit_traffic_quota"]) || ($new_traffic_quota < 0 && $client["limit_traffic_quota"] >= 0)) {
					$max_free_quota = floor($client["limit_traffic_quota"] - $trafficquota);
					if($max_free_quota < 0) $max_free_quota = 0;
					$app->tform->errorMessage .= $app->tform->lng("limit_traffic_quota_free_txt").": ".$max_free_quota." MB<br>";
					// Set the quota field to the max free space
					$this->dataRecord["traffic_quota"] = $max_free_quota;
				}
				unset($tmp);
				unset($tmp_quota);
			}
			
			if($client['parent_client_id'] > 0) {
				// Get the limits of the reseller
				$reseller = $app->db->queryOneRecord("SELECT limit_traffic_quota, limit_web_subdomain, default_webserver, limit_web_quota FROM client WHERE client_id = ".$client['parent_client_id']);

				//* Check the website quota of the client
				if(isset($_POST["hd_quota"]) && $reseller["limit_web_quota"] >= 0) {
					$tmp = $app->db->queryOneRecord("SELECT sum(hd_quota) as webquota FROM web_domain WHERE domain_id != ".intval($this->id)." AND ".$app->tform->getAuthSQL('u'));
					$webquota = $tmp["webquota"];
					$new_web_quota = intval($this->dataRecord["hd_quota"]);
					if(($webquota + $new_web_quota > $reseller["limit_web_quota"]) || ($new_web_quota < 0 && $reseller["limit_web_quota"] >= 0)) {
						$max_free_quota = floor($reseller["limit_web_quota"] - $webquota);
						if($max_free_quota < 0) $max_free_quota = 0;
						$app->tform->errorMessage .= $app->tform->lng("limit_web_quota_free_txt").": ".$max_free_quota." MB<br>";
						// Set the quota field to the max free space
						$this->dataRecord["hd_quota"] = $max_free_quota;
					}
					unset($tmp);
					unset($tmp_quota);
				}

				//* Check the traffic quota of the client
				if(isset($_POST["traffic_quota"]) && $reseller["limit_traffic_quota"] > 0) {
					$tmp = $app->db->queryOneRecord("SELECT sum(traffic_quota) as trafficquota FROM web_domain WHERE domain_id != ".intval($this->id)." AND ".$app->tform->getAuthSQL('u'));
					$trafficquota = $tmp["trafficquota"];
					$new_traffic_quota = intval($this->dataRecord["traffic_quota"]);
					if(($trafficquota + $new_traffic_quota > $reseller["limit_traffic_quota"]) || ($new_traffic_quota < 0 && $reseller["limit_traffic_quota"] >= 0)) {
						$max_free_quota = floor($reseller["limit_traffic_quota"] - $trafficquota);
						if($max_free_quota < 0) $max_free_quota = 0;
						$app->tform->errorMessage .= $app->tform->lng("limit_traffic_quota_free_txt").": ".$max_free_quota." MB<br>";
						// Set the quota field to the max free space
						$this->dataRecord["traffic_quota"] = $max_free_quota;
					}
					unset($tmp);
					unset($tmp_quota);
				}
			}

			// When the record is updated
			if($this->id > 0) {
                // restore the server ID if the user is not admin and record is edited
				$tmp = $app->db->queryOneRecord("SELECT server_id, `web_folder`, `cgi`, `ssi`, `perl`, `ruby`, `python`, `suexec`, `errordocs`, `subdomain`, `ssl` FROM web_domain WHERE domain_id = ".intval($this->id));
                $this->dataRecord['web_folder'] = $tmp['web_folder']; // cannot be changed!
                
                // set the settings to current if not provided (or cleared due to limits)
                if($this->dataRecord['cgi'] == '-') $this->dataRecord['cgi'] = $tmp['cgi'];
                if($this->dataRecord['ssi'] == '-') $this->dataRecord['ssi'] = $tmp['ssi'];
                if($this->dataRecord['perl'] == '-') $this->dataRecord['perl'] = $tmp['perl'];
                if($this->dataRecord['ruby'] == '-') $this->dataRecord['ruby'] = $tmp['ruby'];
                if($this->dataRecord['python'] == '-') $this->dataRecord['python'] = $tmp['python'];
                if($this->dataRecord['suexec'] == '-') $this->dataRecord['suexec'] = $tmp['suexec'];
                if($this->dataRecord['errordocs'] == '-') $this->dataRecord['errordocs'] = $tmp['errordocs'];
                if($this->dataRecord['subdomain'] == '-') $this->dataRecord['subdomain'] = $tmp['subdomain'];
                if($this->dataRecord['ssl'] == '-') $this->dataRecord['ssl'] = $tmp['ssl'];
                
				unset($tmp);
				// When the record is inserted
			} else {
				// Check if the user may add another web_domain
				if($client["limit_web_subdomain"] >= 0) {
					$tmp = $app->db->queryOneRecord("SELECT count(domain_id) as number FROM web_domain WHERE sys_groupid = $client_group_id and (type = 'subdomain' OR type = 'vhostsubdomain')");
					if($tmp["number"] >= $client["limit_web_subdomain"]) {
						$app->error($app->tform->wordbook["limit_web_subdomain_txt"]);
					}
				}
			}
		}
		
		//* make sure that the domain is lowercase
		if(isset($this->dataRecord["domain"])) $this->dataRecord["domain"] = strtolower($this->dataRecord["domain"]);
		
		//* get the server config for this server
		$app->uses("getconf");
		$web_config = $app->getconf->get_server_config(intval(isset($this->dataRecord["server_id"]) ? $this->dataRecord["server_id"] : 0),'web');
		//* Check for duplicate ssl certs per IP if SNI is disabled
		if(isset($this->dataRecord['ssl']) && $this->dataRecord['ssl'] == 'y' && $web_config['enable_sni'] != 'y') {
			$sql = "SELECT count(domain_id) as number FROM web_domain WHERE `ssl` = 'y' AND ip_address = '".$app->db->quote($this->dataRecord['ip_address'])."' and domain_id != ".$this->id;
			$tmp = $app->db->queryOneRecord($sql);
			if($tmp['number'] > 0) $app->tform->errorMessage .= $app->tform->lng("error_no_sni_txt");
		}
		
		// Check if pm.max_children >= pm.max_spare_servers >= pm.start_servers >= pm.min_spare_servers > 0
		if(isset($this->dataRecord['pm_max_children']) && $this->dataRecord['pm'] == 'dynamic') {
			if(intval($this->dataRecord['pm_max_children']) >= intval($this->dataRecord['pm_max_spare_servers']) && intval($this->dataRecord['pm_max_spare_servers']) >= intval($this->dataRecord['pm_start_servers']) && intval($this->dataRecord['pm_start_servers']) >= intval($this->dataRecord['pm_min_spare_servers']) && intval($this->dataRecord['pm_min_spare_servers']) > 0){
		
			} else {
				$app->tform->errorMessage .= $app->tform->lng("error_php_fpm_pm_settings_txt").'<br>';
			}
		}

		parent::onSubmit();
	}

	function onAfterInsert() {
		global $app, $conf;

		// Get configuration for the web system
		$app->uses("getconf");
		$web_rec = $app->tform->getDataRecord($this->id);
		$web_config = $app->getconf->get_server_config(intval($web_rec["server_id"]),'web');
        var_dump($this->parent_domain_record, $web_rec);
		// Set the values for document_root, system_user and system_group
		$system_user = $app->db->quote($this->parent_domain_record['system_user']);
		$system_group = $app->db->quote($this->parent_domain_record['system_group']);
		$document_root = $app->db->quote($this->parent_domain_record['document_root']);
		$php_open_basedir = str_replace("[website_path]/web",$document_root.'/'.$web_rec['web_folder'],$web_config["php_open_basedir"]);
		$php_open_basedir = str_replace("[website_domain]/web",$web_rec['domain'].'/'.$web_rec['web_folder'],$php_open_basedir);
		$php_open_basedir = str_replace("[website_path]",$document_root,$php_open_basedir);
		$php_open_basedir = $app->db->quote(str_replace("[website_domain]",$web_rec['domain'],$php_open_basedir));
		$htaccess_allow_override = $app->db->quote($this->parent_domain_record['allow_override']);

		$sql = "UPDATE web_domain SET sys_groupid = ".intval($this->parent_domain_record['sys_groupid']).",system_user = '$system_user', system_group = '$system_group', document_root = '$document_root', allow_override = '$htaccess_allow_override', php_open_basedir = '$php_open_basedir'  WHERE domain_id = ".$this->id;
		$app->db->query($sql);
	}

	function onBeforeUpdate () {
		global $app, $conf;

		//* Check that all fields for the SSL cert creation are filled
		if(isset($this->dataRecord['ssl_action']) && $this->dataRecord['ssl_action'] == 'create') {
			if($this->dataRecord['ssl_state'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_state_empty').'<br />';
			if($this->dataRecord['ssl_locality'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_locality_empty').'<br />';
			if($this->dataRecord['ssl_organisation'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_organisation_empty').'<br />';
			if($this->dataRecord['ssl_organisation_unit'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_organisation_unit_empty').'<br />';
			if($this->dataRecord['ssl_country'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_country_empty').'<br />';
		}
		
		if(isset($this->dataRecord['ssl_action']) && $this->dataRecord['ssl_action'] == 'save') {
			if(trim($this->dataRecord['ssl_cert']) == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_cert_empty').'<br />';
		}

	}

	function onAfterUpdate() {
		global $app, $conf;

		// Get configuration for the web system
		$app->uses("getconf");
		$web_rec = $app->tform->getDataRecord($this->id);
		$web_config = $app->getconf->get_server_config(intval($web_rec["server_id"]),'web');

		// Set the values for document_root, system_user and system_group
		$system_user = $app->db->quote($this->parent_domain_record['system_user']);
		$system_group = $app->db->quote($this->parent_domain_record['system_group']);
		$document_root = $app->db->quote($this->parent_domain_record['document_root']);
		$php_open_basedir = str_replace("[website_path]/web",$document_root.'/'.$web_rec['web_folder'],$web_config["php_open_basedir"]);
		$php_open_basedir = str_replace("[website_domain]/web",$web_rec['domain'].'/'.$web_rec['web_folder'],$php_open_basedir);
		$php_open_basedir = str_replace("[website_path]",$document_root,$php_open_basedir);
		$php_open_basedir = $app->db->quote(str_replace("[website_domain]",$web_rec['domain'],$php_open_basedir));
		$htaccess_allow_override = $app->db->quote($this->parent_domain_record['allow_override']);

		$sql = "UPDATE web_domain SET sys_groupid = ".intval($this->parent_domain_record['sys_groupid']).",system_user = '$system_user', system_group = '$system_group', document_root = '$document_root', allow_override = '$htaccess_allow_override', php_open_basedir = '$php_open_basedir'  WHERE domain_id = ".$this->id;
		$app->db->query($sql);
	}

}

$page = new page_action;
$page->onLoad();

?>