<?php

/*
Copyright (c) 2009, Till Brehm, projektfarm Gmbh
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

class bind_plugin {

	var $plugin_name = 'bind_plugin';
	var $class_name  = 'bind_plugin';
	var $action = 'update';

	//* This function is called during ispconfig installation to determine
	//  if a symlink shall be created for this plugin.
	function onInstall() {
		global $conf;

		if(isset($conf['bind']['installed']) && $conf['bind']['installed'] == true && @is_link('/usr/local/ispconfig/server/mods-enabled/dns_module.inc.php')) {
			return true;
		} else {
			return false;
		}

	}


	/*
	 	This function is called when the plugin is loaded
	*/

	function onLoad() {
		global $app;

		/*
		Register for the events
		*/

		//* SOA
		$app->plugins->registerEvent('dns_soa_insert', $this->plugin_name, 'soa_insert');
		$app->plugins->registerEvent('dns_soa_update', $this->plugin_name, 'soa_update');
		$app->plugins->registerEvent('dns_soa_delete', $this->plugin_name, 'soa_delete');

		//* SLAVE
		$app->plugins->registerEvent('dns_slave_insert', $this->plugin_name, 'slave_insert');
		$app->plugins->registerEvent('dns_slave_update', $this->plugin_name, 'slave_update');
		$app->plugins->registerEvent('dns_slave_delete', $this->plugin_name, 'slave_delete');

		//* RR
		$app->plugins->registerEvent('dns_rr_insert', $this->plugin_name, 'rr_insert');
		$app->plugins->registerEvent('dns_rr_update', $this->plugin_name, 'rr_update');
		$app->plugins->registerEvent('dns_rr_delete', $this->plugin_name, 'rr_delete');

	}

	//* This creates DNSSEC-Keys and calls soa_dnssec_update.
	function soa_dnssec_create(&$data) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		$domain = substr($data['new']['origin'], 0, strlen($data['new']['origin'])-1);
		if (!file_exists($dns_config['bind_zonefiles_dir'].'/'.$dns_config['bind_zonefiles_masterprefix'].$domain)) return false;

		//* Check Entropy
		if (file_get_contents('/proc/sys/kernel/random/entropy_avail') < 200) {
			$app->log('DNSSEC ERROR: We are low on entropy. Not generating new Keys for '.$domain.'. Please consider installing package haveged.', LOGLEVEL_WARN);
			echo "DNSSEC ERROR: We are low on entropy. Not generating new Keys for $domain. Please consider installing package haveged.\n";
			return false;
		}

		//* Verify that we do not already have keys (overwriting-protection)
		if($data['old']['dnssec_algo'] == $data['new']['dnssec_algo']) {
			if (file_exists($dns_config['bind_keyfiles_dir'].'/dsset-'.$domain.'.')) {
				return $this->soa_dnssec_update($data);
			} else if ($data['new']['dnssec_initialized'] == 'Y') { //In case that we generated keys but the dsset-file was not generated
				$keycount=0;
				foreach (glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'*.key') as $keyfile) {
					$keycount++;
				}
				if ($keycount > 0) {
					$this->soa_dnssec_sign($data);
					return true;
				}
			}
		}

		// Get DNSSEC Algorithms
		$dnssec_algo = explode(',',$data['new']['dnssec_algo']);

		//* Create the Zone Signing and Key Signing Keys
		if(in_array('ECDSAP256SHA256',$dnssec_algo) && count(glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+013*.key')) == 0) {
			$app->system->exec_safe('cd ?; dnssec-keygen -3 -a ECDSAP256SHA256 -n ZONE ?; dnssec-keygen -f KSK -3 -a ECDSAP256SHA256 -n ZONE ?', $dns_config['bind_keyfiles_dir'], $domain, $domain);
		}
		if(in_array('NSEC3RSASHA1',$dnssec_algo) && count(glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+007*.key')) == 0) {
			$app->system->exec_safe('cd ?; dnssec-keygen -a NSEC3RSASHA1 -b 2048 -n ZONE ?; dnssec-keygen -f KSK -a NSEC3RSASHA1 -b 4096 -n ZONE ?', $dns_config['bind_keyfiles_dir'], $domain, $domain);
		}

		$this->soa_dnssec_sign($data); //Now sign the zone for the first time
		$data['new']['dnssec_initialized']='Y';
	}

	function soa_dnssec_sign(&$data) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		$filespre = $dns_config['bind_zonefiles_masterprefix'];
		$domain = substr($data['new']['origin'], 0, strlen($data['new']['origin'])-1);
		if (!file_exists($dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain)) return false;

		//* Get DNSSEC Algorithms
		$dnssec_algo = explode(',',$data['new']['dnssec_algo']);

		//* Get Zone file content
		$zonefile = file_get_contents($dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain);
		$keycount=0;

		//* Include ECDSAP256SHA256 keys in zone
		if(in_array('ECDSAP256SHA256',$dnssec_algo)) {
			foreach (glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+013*.key') as $keyfile) {
				$includeline = '$INCLUDE ' . $keyfile;
				if (!preg_match('@'.preg_quote($includeline).'@', $zonefile)) $zonefile .= "\n".$includeline."\n";
				$keycount++;
			}
		}

		//* Include NSEC3RSASHA1 keys in zone
		if(in_array('NSEC3RSASHA1',$dnssec_algo)) {
			foreach (glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+007*.key') as $keyfile) {
				$includeline = '$INCLUDE ' . $keyfile;
				if (!preg_match('@'.preg_quote($includeline).'@', $zonefile)) $zonefile .= "\n".$includeline."\n";
				$keycount++;
			}
		}

		$keycount_wanted = count(explode(',',$data['new']['dnssec_algo']))*2;

		if ($keycount != $keycount_wanted) $app->log('DNSSEC Warning: There are more or less than 2 keyfiles for each algorithm for zone '.$domain.'. Found: '.$keycount. ' Expected: '.$keycount_wanted, LOGLEVEL_WARN);
		file_put_contents($dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain, $zonefile);

		//* Sign the zone and set it valid for max. 16 days
		// $app->system->exec_safe('cd ?; dnssec-signzone -A -e +1382400 -3 $(head -c 1000 /dev/random | sha1sum | cut -b 1-16) -N increment -o ? -K ? -t ?', $dns_config['bind_keyfiles_dir'], $domain, $dns_config['bind_keyfiles_dir'], $dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain);
        $app->system->exec_safe('cd ?; dnssec-signzone -A -e +1382400 -3 - -N increment -o ? -K ? -t ?', $dns_config['bind_keyfiles_dir'], $domain, $dns_config['bind_keyfiles_dir'], $dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain);

		//* Write Data back ino DB
		$dnssecdata = "DS-Records:\n".file_get_contents($dns_config['bind_keyfiles_dir'].'/dsset-'.$domain.'.');
		$dnssecdata .= "\n------------------------------------\n\nDNSKEY-Records:\n";

		if(in_array('ECDSAP256SHA256',$dnssec_algo)) {
			foreach (glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+013*.key') as $keyfile) {
				$dnssecdata .= file_get_contents($keyfile)."\n\n";
			}
		}

		if(in_array('NSEC3RSASHA1',$dnssec_algo)) {
			foreach (glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+007*.key') as $keyfile) {
				$dnssecdata .= file_get_contents($keyfile)."\n\n";
			}
		}

		if ($app->running_on_slaveserver()) $app->dbmaster->query('UPDATE dns_soa SET dnssec_info=?, dnssec_initialized=\'Y\', dnssec_last_signed=? WHERE id=?', $dnssecdata, intval(time()), intval($data['new']['id']));
		$app->db->query('UPDATE dns_soa SET dnssec_info=?, dnssec_initialized=\'Y\', dnssec_last_signed=? WHERE id=?', $dnssecdata, intval(time()), intval($data['new']['id']));
	}

	function soa_dnssec_update(&$data, $new=false) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		$filespre = $dns_config['bind_zonefiles_masterprefix'];
		$domain = substr($data['new']['origin'], 0, strlen($data['new']['origin'])-1);
		if (!file_exists($dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain)) return false;

		//* Check for available entropy
		if (file_get_contents('/proc/sys/kernel/random/entropy_avail') < 200) {
			$app->log('DNSSEC ERROR: We are low on entropy. This could cause server script to fail. Please consider installing package haveged.', LOGLEVEL_ERROR);
			echo "DNSSEC ERROR: We are low on entropy. This could cause server script to fail. Please consider installing package haveged.\n";
			return false;
		}

		if (!$new && !file_exists($dns_config['bind_keyfiles_dir'].'/dsset-'.$domain.'.')) $this->soa_dnssec_create($data);

		$dbdata = $app->db->queryOneRecord('SELECT id,serial FROM dns_soa WHERE id=?', intval($data['new']['id']));
		$app->system->exec_safe('cd ?; named-checkzone ? ? | egrep -ho \'[0-9]{10}\'', $dns_config['bind_zonefiles_dir'], $domain, $dns_config['bind_zonefiles_dir'].'/'.$filespre.$domain);
		$retState = $app->system->last_exec_retcode();
		if ($retState != 0) {
			$app->log('DNSSEC Error: Error in Zonefile for '.$domain, LOGLEVEL_ERROR);
			return false;
		}

		$this->soa_dnssec_sign($data);
	}

	function soa_dnssec_delete(&$data, $sql_update = true) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

        if(isset($data['new']['origin'])) {
		$domain = substr($data['new']['origin'], 0, strlen($data['new']['origin'])-1);
        } elseif (isset($data['old']['origin'])) {
            $domain = substr($data['old']['origin'], 0, strlen($data['old']['origin'])-1);
        } else {
            //* We have no domain
            $app->log('DNSSEC Delete: Unable to find domain', LOGLEVEL_WARN);
            return;
        }

        //* Delete key files
		$key_files = glob($dns_config['bind_keyfiles_dir'].'/K'.$domain.'.+*');
		foreach($key_files as $file) {
			unlink($file);
		}

        //* Delete signed zone file
        $signed_zone_file = $dns_config['bind_zonefiles_dir'].'/'.$dns_config['bind_zonefiles_masterprefix'].$domain.'.signed';
		if(file_exists($signed_zone_file)) unlink($signed_zone_file);

        //* Delete dsset file
        $dsset_file = $dns_config['bind_keyfiles_dir'].'/dsset-'.$domain.'.';
		if(file_exists($dsset_file)) unlink($dsset_file);

        //* Update DNSSEC info in database
        if($sql_update) {
		    if ($app->running_on_slaveserver()) $app->dbmaster->query('UPDATE dns_soa SET dnssec_info=\'\', dnssec_initialized=\'N\' WHERE id=?', intval($data['new']['id']));
		    $app->db->query('UPDATE dns_soa SET dnssec_info=\'\', dnssec_initialized=\'N\' WHERE id=?', intval($data['new']['id']));
        }
	}

	function soa_insert($event_name, $data) {
		global $app, $conf;

		$this->action = 'insert';
		$this->soa_update($event_name, $data);

	}

	function soa_update($event_name, $data) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		//* Get the bind version
		$bind_caa = false;
        $bind = explode("\n", shell_exec('which named bind 2> /dev/null'));
        $bind = reset($bind);
        if(is_executable($bind)) {
			exec($bind . ' -v 2>&1', $tmp);
			$bind_caa = @(version_compare($tmp[0],"BIND 9.9.6", '>='))?true:false;
			unset($tmp);
		}
		unset($bind);

		//* Write the domain file
		if(!empty($data['new']['id'])) {
			$tpl = new tpl();
			$tpl->newTemplate("bind_pri.domain.master");

			$zone = $data['new'];
			$tpl->setVar($zone);

			$records = $app->db->queryAllRecords("SELECT * FROM dns_rr WHERE zone = ? AND active = 'Y'", $zone['id']);
			if(is_array($records) && !empty($records)){
				$caa_add_rec = -1;
				for($i=0;$i<sizeof($records);$i++){
					if($records[$i]['ttl'] == 0) $records[$i]['ttl'] = '';
					if($records[$i]['name'] == '') $records[$i]['name'] = '@';
					//* Split TXT records, if nescessary
					if($records[$i]['type'] == 'TXT' && strlen($records[$i]['data']) > 255) {
						$records[$i]['data'] = implode('" "',str_split( $records[$i]['data'], 255));
					}
					//* CAA-Records - Type257 for older bind-versions
					if($records[$i]['type'] == 'CAA' && !$bind_caa) {
						$records[$i]['type'] = 'TYPE257';
						$temp = explode(' ', $records[$i]['data']);
						unset($temp[0]);
						$records[$i]['data'] = implode(' ', $temp);
						$data_new = str_replace(array('"', ' '), '', $records[$i]['data']);
						$hex = unpack('H*', $data_new);
						if ($temp[1] == 'issuewild') {
							$hex[1] = '0009'.strtoupper($hex[1]);
							if ($caa_add_rec == -1) {
								// add issue ";" if only issuewild recordsa
								$caa_add_rec = array_push($records, $records[$i]);
								$records[$caa_add_rec-1]['data'] = "\# 8 000569737375653B";
							}
						} else {
							$hex[1] = '0005'.strtoupper($hex[1]);
							if ($caa_add_rec > 0) {
								// remove previously added issue ";" 
								array_pop($records);
							}
							$caa_add_rec = -2;
						}
						$length = strlen($hex[1])/2;
						$data_new = "\# $length $hex[1]";
						$records[$i]['data'] = $data_new;
					}
				}
			}
			else {
				$app->log("DNS zone[".$zone['origin']."] has no records yet, skip...", LOGLEVEL_DEBUG);
				return;
			}
			$tpl->setLoop('zones', $records);

			$filename = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_masterprefix'] . str_replace("/", "_", substr($zone['origin'], 0, -1));

			$old_zonefile = @file_get_contents($filename);
			$rendered_zone = $tpl->grab();
			file_put_contents($filename, $rendered_zone);

			chown($filename, $dns_config['bind_user']);
			chgrp($filename, $dns_config['bind_group']);

			// Store also in the db for exports.
			$app->dbmaster->query("UPDATE `dns_soa` SET `rendered_zone`=? WHERE id=?", $rendered_zone, $zone['id']);

			//* Check the zonefile
			if(is_file($filename.'.err')) unlink($filename.'.err');
			$app->system->exec_safe('named-checkzone ? ?', $zone['origin'], $filename);
			$out = $app->system->last_exec_out();
			$return_status = $app->system->last_exec_retcode();
			if($return_status === 0) {
				$app->log("Writing BIND domain file: ".$filename, LOGLEVEL_DEBUG);
			} else {
				$loglevel = @($dns_config['disable_bind_log'] === 'y') ? LOGLEVEL_DEBUG : LOGLEVEL_WARN;
				$app->log("Writing BIND domain file failed: ".$filename." ".implode(' ', $out), $loglevel);
				if(is_array($out) && !empty($out)){
					$app->log('Reason for Bind zone check failure: '.implode("\n", $out), $loglevel);
					$app->dbmaster->datalogError(implode("\n", $out));
				}
				if ($old_zonefile != '') {
					rename($filename, $filename.'.err');
					file_put_contents($filename, $old_zonefile);
					chown($filename, $dns_config['bind_user']);
					chgrp($filename, $dns_config['bind_group']);
				} else {
					rename($filename, $filename.'.err');
				}
			}
			unset($tpl);
			unset($records);
			unset($records_out);
			unset($zone);
		}

		//* DNSSEC-Implementation
		if($data['old']['origin'] != $data['new']['origin']) {
			if (@$data['old']['dnssec_initialized'] == 'Y' && strlen(@$data['old']['origin']) > 3) $this->soa_dnssec_delete($data); //delete old keys
			if ($data['new']['dnssec_wanted'] == 'Y') $this->soa_dnssec_create($data);
		} elseif($data['old']['dnssec_algo'] != $data['new']['dnssec_algo']) {
			$app->log("DNSSEC Algorithm has changed: ".$data['new']['dnssec_algo'], LOGLEVEL_DEBUG);
			if ($data['new']['dnssec_wanted'] == 'Y') $this->soa_dnssec_create($data);
		} elseif ($data['new']['dnssec_wanted'] == 'Y' && $data['old']['dnssec_initialized'] == 'N') {
			$this->soa_dnssec_create($data);
		} elseif ($data['new']['dnssec_wanted'] == 'N' && $data['old']['dnssec_initialized'] == 'Y') {	//delete old signed file if dnssec is no longer wanted
			$filename = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_masterprefix'] . str_replace("/", "_", substr($data['old']['origin'], 0, -1));
			if(is_file($filename.'.signed')) unlink($filename.'.signed');
 		} elseif ($data['new']['dnssec_wanted'] == 'Y') {
			$this->soa_dnssec_update($data);
		}
		// END DNSSEC

		//* rebuild the named.conf file if the origin has changed or when the origin is inserted.
		//if($this->action == 'insert' || $data['old']['origin'] != $data['new']['origin']) {
		$this->write_named_conf($data, $dns_config);
		//}

		//* Delete old domain file, if domain name has been changed
		if(!empty($data['old']['origin']) && $data['old']['origin'] != $data['new']['origin']) {
			$filename = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_masterprefix'] . str_replace("/", "_", substr($data['old']['origin'], 0, -1));

			if(is_file($filename)) unlink($filename);
			if(is_file($filename.'.err')) unlink($filename.'.err');
			if(is_file($filename.'.signed')) unlink($filename.'.signed');
 		}

		//* Restart bind nameserver if update_acl is not empty, otherwise reload it
               if(!empty($data['new']['update_acl'])) {
			$app->services->restartServiceDelayed('bind', 'restart');
		} else {
			$app->services->restartServiceDelayed('bind', 'reload');
		}

	}

	function soa_delete($event_name, $data) {
		global $app, $conf;

		//* load the server configuration options
		$app->uses("getconf,tpl");
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		//* rebuild the named.conf file
		$this->write_named_conf($data, $dns_config);

        //* Delete DNSSEC files
        $this->soa_dnssec_delete($data,false);

		//* Delete the domain file
		$zone_file_name = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_masterprefix'] . str_replace("/", "_", substr($data['old']['origin'], 0, -1));
		if(is_file($zone_file_name)) unlink($zone_file_name);
		if(is_file($zone_file_name.'.err')) unlink($zone_file_name.'.err');
		$app->log("Deleting BIND domain file: ".$zone_file_name, LOGLEVEL_DEBUG);

		//* Reload bind nameserver
		$app->services->restartServiceDelayed('bind', 'reload');

	}

	function slave_insert($event_name, $data) {
		global $app, $conf;

		$this->action = 'insert';
		$this->slave_update($event_name, $data);

	}

	function slave_update($event_name, $data) {
		global $app, $conf;

		//* Load libraries
		$app->uses("getconf,tpl");

		//* load the server configuration options
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		//* rebuild the named.conf file if the origin has changed or when the origin is inserted.
		//if($this->action == 'insert' || $data['old']['origin'] != $data['new']['origin']) {
		$this->write_named_conf($data, $dns_config);
		//}

		//* Delete old domain file, if domain name has been changed
		if($data['old']['origin'] != $data['new']['origin']) {
			$filename = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_masterprefix'] . str_replace("/", "_", substr($data['old']['origin'], 0, -1));
			if(is_file($filename)) unset($filename);
		}

		//* Ensure that the named slave directory is writable by the named user
		if(!empty($dns_config['bind_zonefiles_slaveprefix'])) {
			$slave_record_dir = $dns_config['bind_zonefiles_dir'].'/'.$dns_config['bind_zonefiles_slaveprefix'];
			if(substr($slave_record_dir,-1) != '/') $slave_record_dir = dirname($slave_record_dir);
		} else {
			$slave_record_dir = $dns_config['bind_zonefiles_dir'];
		}
		if(!@is_dir($slave_record_dir)) mkdir($slave_record_dir, 0770, true);
		chown($slave_record_dir, $dns_config['bind_user']);
		chgrp($slave_record_dir, $dns_config['bind_group']);

		//* Reload bind nameserver
		$app->services->restartServiceDelayed('bind', 'reload');

	}

	function slave_delete($event_name, $data) {
		global $app, $conf;


		//* load the server configuration options
		$app->uses("getconf,tpl");
		$dns_config = $app->getconf->get_server_config($conf["server_id"], 'dns');

		//* rebuild the named.conf file
		$this->write_named_conf($data, $dns_config);

		//* Delete the domain file
		$zone_file_name = $dns_config['bind_zonefiles_dir'].'/' . $dns_config['bind_zonefiles_slaveprefix'] . str_replace("/", "_", substr($data['old']['origin'], 0, -1));
		if(is_file($zone_file_name)) unlink($zone_file_name);
		$app->log("Deleting BIND domain file for secondary zone: ".$zone_file_name, LOGLEVEL_DEBUG);

		//* Reload bind nameserver
		$app->services->restartServiceDelayed('bind', 'reload');


	}

	function rr_insert($event_name, $data) {
		global $app, $conf;

		//* Get the data of the soa and call soa_update
		$tmp = $app->db->queryOneRecord("SELECT * FROM dns_soa WHERE id = ?", $data['new']['zone']);
		$data["new"] = $tmp;
		$data["old"] = $tmp;
		$this->action = 'update';

		if (isset($data['new']['active']) && $data['new']['active'] == 'Y') {
			$this->soa_update($event_name, $data);
		}

	}

	function rr_update($event_name, $data) {
		global $app, $conf;

		//* Get the data of the soa and call soa_update
		$tmp = $app->db->queryOneRecord("SELECT * FROM dns_soa WHERE id = ?", $data['new']['zone']);
		$data["new"] = $tmp;
		$data["old"] = $tmp;
		$this->action = 'update';
		$this->soa_update($event_name, $data);

	}

	function rr_delete($event_name, $data) {
		global $app, $conf;

		//* Get the data of the soa and call soa_update
		//* In a single server setup the record in dns_soa will already be gone ... so this will give an empty array.
		$tmp = $app->db->queryOneRecord("SELECT * FROM dns_soa WHERE id = ?", $data['old']['zone']);
		$data["new"] = $tmp;
		$data["old"] = $tmp;
		$this->action = 'update';

		if (isset($data['new']['active']) && $data['new']['active'] == 'Y') {
			$this->soa_update($event_name, $data);
		}

	}

	//##################################################################

	function write_named_conf($data, $dns_config) {
		global $app, $conf;

		//* Only write the master file for the current server
		$tmps = $app->db->queryAllRecords("SELECT origin, xfer, also_notify, update_acl, dnssec_wanted FROM dns_soa WHERE active = 'Y' AND server_id=?", $conf["server_id"]);
		$zones = array();

		//* Check if the current zone that triggered this function has at least one NS record

		$pri_zonefiles_path = $dns_config['bind_zonefiles_dir'].'/'.$dns_config['bind_zonefiles_masterprefix'];
		$sec_zonefiles_path = $dns_config['bind_zonefiles_dir'].'/'.$dns_config['bind_zonefiles_slaveprefix'];

		//* Loop trough zones
		foreach($tmps as $tmp) {
			$zone_file = $pri_zonefiles_path.str_replace("/", "_", substr($tmp['origin'], 0, -1));
			if ($tmp['dnssec_wanted'] == 'Y') $zone_file .= '.signed'; //.signed is for DNSSEC-Implementation

			$options = '';
			if($tmp['xfer'] != null && trim($tmp['xfer']) != '') {
				$options .= "        allow-transfer {".str_replace(',', ';', $tmp['xfer']).";};\n";
			}
			if($tmp['also_notify'] != null && trim($tmp['also_notify']) != '') $options .= '        also-notify {'.str_replace(',', ';', $tmp['also_notify']).";};\n";
			if($tmp['update_acl'] != null && trim($tmp['update_acl']) != '') $options .= "        allow-update {".str_replace(',', ';', $tmp['update_acl']).";};\n";

			if(file_exists($zone_file)) {
				$zones[] = array( 'zone' => substr($tmp['origin'], 0, -1),
					'zonefile_path' => $zone_file,
					'options' => $options
				);
			}
		}

		$tpl = new tpl();
		$tpl->newTemplate("bind_named.conf.local.master");
		$tpl->setLoop('zones', $zones);

		//* And loop through the secondary zones, but only for the current server
		$tmps_sec = $app->db->queryAllRecords("SELECT origin, xfer, ns FROM dns_slave WHERE active = 'Y' AND server_id=?", $conf["server_id"]);
		$zones_sec = array();

		foreach($tmps_sec as $tmp) {

			// When you have more than one master, the serial number is used to determine which Master has the most current version of the zone by the
			// slaves.  The slaves actually ask for the SOA record from each Master when refreshing.
			$options = "        masters {".str_replace(',', ';', $tmp['ns']).";};\n";
			if(trim($tmp['xfer']) != '') {
				$options .= "        allow-transfer {".str_replace(',', ';', $tmp['xfer']).";};\n";
			} else {
				$options .= "        allow-transfer {none;};\n";
			}


			$zones_sec[] = array( 'zone' => substr($tmp['origin'], 0, -1),
				'zonefile_path' => $sec_zonefiles_path.str_replace("/", "_", substr($tmp['origin'], 0, -1)),
				'options' => $options
			);
		}

		$tpl_sec = new tpl();
		$tpl_sec->newTemplate("bind_named.conf.local.slave");
		$tpl_sec->setLoop('zones', $zones_sec);

		file_put_contents($dns_config['named_conf_local_path'], $tpl->grab()."\n".$tpl_sec->grab());
		$app->log("Writing BIND named.conf.local file: ".$dns_config['named_conf_local_path'], LOGLEVEL_DEBUG);

		unset($tpl_sec);
		unset($zones_sec);
		unset($tmps_sec);
		unset($tpl);
		unset($zones);
		unset($tmps);

	}
} // end class

?>
