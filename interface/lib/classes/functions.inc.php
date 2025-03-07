<?php

/*
Copyright (c) 2010, Till Brehm, projektfarm Gmbh
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

require_once __DIR__.'/../compatibility.inc.php';

//* The purpose of this library is to provide some general functions.
//* This class is loaded automatically by the ispconfig framework.

class functions {
	var $idn_converter = null;
	var $idn_converter_name = '';

	public function mail($to, $subject, $text, $from, $filepath = '', $filetype = 'application/pdf', $filename = '', $cc = '', $bcc = '', $from_name = '') {
		global $app, $conf;

		if($conf['demo_mode'] == true) $app->error("Mail sending disabled in demo mode.");

		$app->uses('getconf,ispcmail');
		$mail_config = $app->getconf->get_global_config('mail');
		if($mail_config['smtp_enabled'] == 'y') {
			$mail_config['use_smtp'] = true;
			$app->ispcmail->setOptions($mail_config);
		}
		$app->ispcmail->setSender($from, $from_name);
		$app->ispcmail->setSubject($subject);
		$app->ispcmail->setMailText($text);

		if($filepath != '') {
			if(!file_exists($filepath)) $app->error("Mail attachement does not exist ".$filepath);
			$app->ispcmail->readAttachFile($filepath);
		}

		if($cc != '') $app->ispcmail->setHeader('Cc', $cc);
		if($bcc != '') $app->ispcmail->setHeader('Bcc', $bcc);

		if(is_string($to) && strpos($to, ',') !== false) {
				$to = preg_split('/\s*,\s*/', $to);
		}

		$app->ispcmail->send($to);
		$app->ispcmail->finish();

		return true;
	}

	public function array_merge($array1, $array2) {
		$out = $array1;
		foreach($array2 as $key => $val) {
			$out[$key] = $val;
		}
		return $out;
	}

	public function currency_format($number, $view = '') {
		global $app;
		if($view != '') $number_format_decimals = (int)$app->lng('number_format_decimals_'.$view);
		if(!$number_format_decimals) $number_format_decimals = (int)$app->lng('number_format_decimals');

		$number_format_dec_point = $app->lng('number_format_dec_point');
		$number_format_thousands_sep = $app->lng('number_format_thousands_sep');
		if($number_format_thousands_sep == 'number_format_thousands_sep') $number_format_thousands_sep = '';
		return number_format((double)$number, $number_format_decimals, $number_format_dec_point, $number_format_thousands_sep);
	}

	//* convert currency formatted number back to floating number
	public function currency_unformat($number) {
		global $app;

		$number_format_dec_point = $app->lng('number_format_dec_point');
		$number_format_thousands_sep = $app->lng('number_format_thousands_sep');
		if($number_format_thousands_sep == 'number_format_thousands_sep') $number_format_thousands_sep = '';

		if($number_format_thousands_sep != '') $number = str_replace($number_format_thousands_sep, '', $number);
		if($number_format_dec_point != '.' && $number_format_dec_point != '') $number = str_replace($number_format_dec_point, '.', $number);

		return (double)$number;
	}

	public function get_ispconfig_url() {
		global $app;

		$url = (stristr($_SERVER['SERVER_PROTOCOL'], 'HTTPS') || stristr($_SERVER['HTTPS'], 'on'))?'https':'http';
		if($_SERVER['SERVER_NAME'] != '_') {
			$url .= '://'.$_SERVER['SERVER_NAME'];
			if($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
				$url .= ':'.$_SERVER['SERVER_PORT'];
			}
		} else {
			$app->uses("getconf");
			$server_config = $app->getconf->get_server_config(1, 'server');
			$url .= '://'.$server_config['hostname'];
			if($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
				$url .= ':'.$_SERVER['SERVER_PORT'];
			}
		}
		return $url;
	}

	public function json_encode($data) {
		if(!function_exists('json_encode')){
			if(is_array($data) || is_object($data)){
				$islist = is_array($data) && (empty($data) || array_keys($data) === range(0, count($data)-1));

				if($islist){
					$json = '[' . implode(',', array_map(array($this, "json_encode"), $data) ) . ']';
				} else {
					$items = array();
					foreach( $data as $key => $value ) {
						$items[] = $this->json_encode("$key") . ':' . $this->json_encode($value);
					}
					$json = '{' . implode(',', $items) . '}';
				}
			} elseif(is_string($data)){
				// Escape non-printable or Non-ASCII characters.
				// I also put the \\ character first, as suggested in comments on the 'addclashes' page.
				$string = '"'.addcslashes($data, "\\\"\n\r\t/".chr(8).chr(12)).'"';
				$json = '';
				$len = strlen($string);
				// Convert UTF-8 to Hexadecimal Codepoints.
				for($i = 0; $i < $len; $i++){
					$char = $string[$i];
					$c1 = ord($char);

					// Single byte;
					if($c1 <128){
						$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
						continue;
					}

					// Double byte
					$c2 = ord($string[++$i]);
					if(($c1 & 32) === 0){
						$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
						continue;
					}

					// Triple
					$c3 = ord($string[++$i]);
					if(($c1 & 16) === 0){
						$json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128));
						continue;
					}

					// Quadruple
					$c4 = ord($string[++$i]);
					if(($c1 & 8) === 0){
						$u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1;

						$w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3);
						$w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128);
						$json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
					}
				}
			} else {
				// int, floats, bools, null
				$json = strtolower(var_export($data, true));
			}
			return $json;
		} else {
			return json_encode($data);
		}
	}

	public function suggest_ips($type = 'IPv4'){
		global $app;

		if($type == 'IPv4'){
//			$regex = "/^[0-9]{1,3}(\.)[0-9]{1,3}(\.)[0-9]{1,3}(\.)[0-9]{1,3}$/";
			$regex = "/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/";
		} else {
			// IPv6
			$regex = "/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/";
		}

		$server_by_id = array();
		$server_by_ip = array();
		$servers = $app->db->queryAllRecords("SELECT * FROM server");
		if(is_array($servers) && !empty($servers)){
			foreach($servers as $server){
				$server_by_id[$server['server_id']] = $server['server_name'];
			}
		}

		$ips = array();
		$results = $app->db->queryAllRecords("SELECT ip_address AS ip, server_id FROM server_ip WHERE ip_type = ?", $type);
		if(!empty($results) && is_array($results)){
			foreach($results as $result){
				if(preg_match($regex, $result['ip'])){
					$ips[] = $result['ip'];
					$server_by_ip[$result['ip']] = $server_by_id[$result['server_id']];
				}
			}
		}
		$results = $app->db->queryAllRecords("SELECT ip_address AS ip FROM openvz_ip");
		if(!empty($results) && is_array($results)){
			foreach($results as $result){
				if(preg_match($regex, $result['ip'])) $ips[] = $result['ip'];
			}
		}
		$results = $app->db->queryAllRecords("SELECT data AS ip FROM dns_rr WHERE type = 'A' OR type = 'AAAA'");
		if(!empty($results) && is_array($results)){
			foreach($results as $result){
				if(preg_match($regex, $result['ip'])) $ips[] = $result['ip'];
			}
		}
		$results = $app->db->queryAllRecords("SELECT ns AS ip FROM dns_slave");
		if(!empty($results) && is_array($results)){
			foreach($results as $result){
				if(preg_match($regex, $result['ip'])) $ips[] = $result['ip'];
			}
		}

		$results = $app->db->queryAllRecords("SELECT remote_ips FROM web_database WHERE remote_ips != ''");
		if(!empty($results) && is_array($results)){
			foreach($results as $result){
				$tmp_ips = explode(',', $result['remote_ips']);
				foreach($tmp_ips as $tmp_ip){
					$tmp_ip = trim($tmp_ip);
					if(preg_match($regex, $tmp_ip)) $ips[] = $tmp_ip;
				}
			}
		}
		$ips = array_unique($ips);
		sort($ips, SORT_NUMERIC);

		$result_array = array('cheader' => array(), 'cdata' => array());

		if(!empty($ips)){
			$result_array['cheader'] = array('title' => 'IPs',
				'total' => count($ips),
				'limit' => count($ips)
			);

			foreach($ips as $ip){
				$result_array['cdata'][] = array( 'title' => $ip,
					'description' => $type.($server_by_ip[$ip] != ''? ' &gt; '.$server_by_ip[$ip] : ''),
					'onclick' => '',
					'fill_text' => $ip
				);
			}
		}

		return $result_array;
	}

	public function intval($string, $force_numeric = false) {
		if(intval($string) == 2147483647 || ($string > 0 && intval($string) < 0)) {
			if($force_numeric == true) return floatval($string);
			elseif(preg_match('/^([-]?)[0]*([1-9][0-9]*)([^0-9].*)*$/', $string, $match)) return $match[1].$match[2];
			else return 0;
		} else {
			return intval($string);
		}
	}

	/**
	 * Function to change bytes to kB, MB, GB or TB
	 * @param int $size - size in bytes
	 * @param int precicion - after-comma-numbers (default: 2)
	 * @return string - formated bytes
	 */
	public function formatBytes($size, $precision = 2) {
		// 0 is a special as it would give NAN otehrwise.
		if ($size == 0) {
			return 0;
		}

		$base=log($size)/log(1024);
		$suffixes=array('', ' kB', ' MB', ' GB', ' TB');
		return round(pow(1024, $base-floor($base)), $precision).$suffixes[floor($base)];
	}

	/**
	 * Function to change bytes to kB, MB, GB or TB or the translated string 'Unlimited' for -1
	 * @param int $size - size in bytes
	 * @param int precicion - after-comma-numbers (default: 2)
	 * @return string - formated bytes
	 */
	public function formatBytesOrUnlimited($size, $precision = 2) {
		global $app;

		if ($size == -1) {
			return $app->lng('unlimited_txt');
		}
		else {
			return $this->formatBytes($size, $precision);
		}

	}

	/**
	 * Normalize a path and strip duplicate slashes from it
	 *
	 * This will also remove all /../ from the path, reducing the preceding path elements
	 *
	 * @param string $path
	 * @return string
	 */
	public function normalize_path($path) {
		$path = preg_replace('~[/]{2,}~', '/', $path);
		$parts = explode('/', $path);
		$return_parts = array();

		foreach($parts as $current_part) {
			if($current_part === '..') {
				if(!empty($return_parts) && end($return_parts) !== '') {
					array_pop($return_parts);
				}
			} else {
				$return_parts[] = $current_part;
			}
		}

		return implode('/', $return_parts);
	}


	/** IDN converter wrapper.
	 * all converter classes should be placed in ISPC_CLASS_PATH.'/idn/'
	 */
	private function _idn_encode_decode($domain, $encode = true) {
		if($domain == '') return '';
		if(preg_match('/^[0-9\.]+$/', $domain)) return $domain; // may be an ip address - anyway does not need to bee encoded

		// get domain and user part if it is an email
		$user_part = false;
		if(strpos($domain, '@') !== false) {
			$user_part = substr($domain, 0, strrpos($domain, '@'));
			$domain = substr($domain, strrpos($domain, '@') + 1);
		}

		// idn_to_* chokes on leading dots, but we need them for amavis, so remove it for later
		if(substr($domain, 0, 1) === '.') {
			$leading_dot = true;
			$domain = substr($domain, 1);
		} else {
			$leading_dot = false;
		}

		if($encode == true) {
			if(function_exists('idn_to_ascii')) {
				if(defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46') && constant('IDNA_NONTRANSITIONAL_TO_ASCII')) {
					$domain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
				} else {
					$domain = idn_to_ascii($domain);
				}
			} elseif(file_exists(ISPC_CLASS_PATH.'/idn/idna_convert.class.php')) {
				/* use idna class:
                 * @author  Matthias Sommerfeld <mso@phlylabs.de>
                 * @copyright 2004-2011 phlyLabs Berlin, http://phlylabs.de
                 * @version 0.8.0 2011-03-11
                 */

				if(!is_object($this->idn_converter) || $this->idn_converter_name != 'idna_convert.class') {
					include_once ISPC_CLASS_PATH.'/idn/idna_convert.class.php';
					$this->idn_converter = new idna_convert(array('idn_version' => 2008));
					$this->idn_converter_name = 'idna_convert.class';
				}
				$domain = $this->idn_converter->encode($domain);
			}
		} else {
			if(function_exists('idn_to_utf8')) {
				if(defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46') && constant('IDNA_NONTRANSITIONAL_TO_ASCII')) {
					$domain = idn_to_utf8($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
				} else {
					$domain = idn_to_utf8($domain);
				}
			} elseif(file_exists(ISPC_CLASS_PATH.'/idn/idna_convert.class.php')) {
				/* use idna class:
                 * @author  Matthias Sommerfeld <mso@phlylabs.de>
                 * @copyright 2004-2011 phlyLabs Berlin, http://phlylabs.de
                 * @version 0.8.0 2011-03-11
                 */

				if(!is_object($this->idn_converter) || $this->idn_converter_name != 'idna_convert.class') {
					include_once ISPC_CLASS_PATH.'/idn/idna_convert.class.php';
					$this->idn_converter = new idna_convert(array('idn_version' => 2008));
					$this->idn_converter_name = 'idna_convert.class';
				}
				$domain = $this->idn_converter->decode($domain);
			}
		}

		if($leading_dot == true) {
			$domain = '.' . $domain;
		}

		if($user_part !== false) return $user_part . '@' . $domain;
		else return $domain;
	}

	public function idn_encode($domain) {
		$domains = explode("\n", $domain);
		for($d = 0; $d < count($domains); $d++) {
			$domains[$d] = $this->_idn_encode_decode($domains[$d], true);
		}
		return implode("\n", $domains);
	}

	public function idn_decode($domain) {
		$domains = explode("\n", $domain);
		for($d = 0; $d < count($domains); $d++) {
			$domains[$d] = $this->_idn_encode_decode($domains[$d], false);
		}
		return implode("\n", $domains);
	}

	public function is_allowed_user($username, $restrict_names = false) {
		global $app;

		$name_blacklist = array('root','ispconfig','vmail','getmail');
		if(in_array($username,$name_blacklist)) return false;

		if(preg_match('/^[a-zA-Z0-9\.\-_]{1,32}$/', $username) == false) return false;

		if($restrict_names == true && preg_match('/^web\d+$/', $username) == false) return false;

		return true;
	}

	public function is_allowed_group($groupname, $restrict_names = false) {
		global $app;

		$name_blacklist = array('root','ispconfig','vmail','getmail');
		if(in_array($groupname,$name_blacklist)) return false;

		if(preg_match('/^[a-zA-Z0-9\.\-_]{1,32}$/', $groupname) == false) return false;

		if($restrict_names == true && preg_match('/^client\d+$/', $groupname) == false) return false;

		return true;
	}

	public function getimagesizefromstring($string){
		if (!function_exists('getimagesizefromstring')) {
			$uri = 'data://application/octet-stream;base64,' . base64_encode($string);
			return getimagesize($uri);
		} else {
			return getimagesizefromstring($string);
		}
	}

	public function password($minLength = 10, $special = false){
		global $app;

		$iteration = 0;
		$password = "";
		$maxLength = $minLength + 5;
		$length = random_int($minLength, $maxLength);

		while($iteration < $length){
			$randomNumber = random_int(33, 126);
			if(!$special){
				if (($randomNumber >=33) && ($randomNumber <=47)) { continue; }
				if (($randomNumber >=58) && ($randomNumber <=64)) { continue; }
				if (($randomNumber >=91) && ($randomNumber <=96)) { continue; }
				if (($randomNumber >=123) && ($randomNumber <=126)) { continue; }
			}
			$iteration++;
			$password .= chr($randomNumber);
		}
		$app->uses('validate_password');
		if($app->validate_password->password_check('', $password, '') !== false) $password = $this->password($minLength, $special);
		return $password;
	}

	public function generate_customer_no(){
		global $app;
		// generate customer no.
		$customer_no = mt_rand(100000, 999999);
		while($app->db->queryOneRecord("SELECT client_id FROM client WHERE customer_no = ?", $customer_no)) {
			$customer_no = mt_rand(100000, 999999);
		}

		return $customer_no;
	}

	public function generate_ssh_key($client_id, $username = ''){
		global $app;

		// generate the SSH key pair for the client
		if (! $tmpdir = $app->system->exec_safe('mktemp -dt id_rsa.XXXXXXXX')) {
			$app->log("mktemp failed, cannot create SSH keypair for ".$username, LOGLEVEL_WARN);
		}
		$id_rsa_file = $tmpdir . uniqid('',true);
		$id_rsa_pub_file = $id_rsa_file.'.pub';
		if(file_exists($id_rsa_file)) unset($id_rsa_file);
		if(file_exists($id_rsa_pub_file)) unset($id_rsa_pub_file);
		if(!file_exists($id_rsa_file) && !file_exists($id_rsa_pub_file)) {
			$app->system->exec_safe('ssh-keygen -t rsa -C ? -f ? -N ""', $username.'-rsa-key-'.time(), $id_rsa_file);
			$app->db->query("UPDATE client SET created_at = UNIX_TIMESTAMP(), id_rsa = ?, ssh_rsa = ? WHERE client_id = ?", @file_get_contents($id_rsa_file), @file_get_contents($id_rsa_pub_file), $client_id);
			$app->system->rmdir($tmpdir, true);
		} else {
			$app->log("Failed to create SSH keypair for ".$username, LOGLEVEL_WARN);
		}
	}

	public function htmlentities($value) {
		global $conf;

		if(is_array($value)) {
			$out = array();
			foreach($value as $key => $val) {
				if(is_array($val)) {
					$out[$key] = $this->htmlentities($val);
				} else {
					$out[$key] = htmlentities($val, ENT_QUOTES, $conf["html_content_encoding"]);
				}
			}
		} else {
			$out = htmlentities($value, ENT_QUOTES, $conf["html_content_encoding"]);
		}

		return $out;
	}

	// Function to check paths before we use it as include. Use with absolute paths only.
	public function check_include_path($path) {
		if(strpos($path,'//') !== false) die('Include path seems to be an URL: '.$this->htmlentities($path));
		if(strpos($path,'..') !== false) die('Two dots are not allowed in include path: '.$this->htmlentities($path));
		if(!preg_match("/^[a-zA-Z0-9_\/\.\-]+$/", $path)) die('Wrong chars in include path: '.$this->htmlentities($path));
		$path = realpath($path);
		if($path == '') die('Include path does not exist.');
		if(substr($path,0,strlen(ISPC_ROOT_PATH)) != ISPC_ROOT_PATH) die('Path '.$this->htmlentities($path).' is outside of ISPConfig installation directory.');
		return $path;
	}

	// Function to check language strings
	public function check_language($language) {
		global $app;
		if(preg_match('/^[a-z]{2}$/',$language)) {
			 return $language;
		} else {
			$app->log('Wrong language string: '.$this->htmlentities($language),1);
			return 'en';
		}
	}

        // Function to lock a client
	public function func_client_lock($client_id,$locked) {
		global $app;
		$client_data = $app->db->queryOneRecord('SELECT `tmp_data` FROM `client` WHERE `client_id` = ?', $client_id);
		if($client_data['tmp_data'] == '') $tmp_data = array();
		else $tmp_data = unserialize($client_data['tmp_data']);
		if(!is_array($tmp_data)) $tmp_data = array();
		$to_disable = array('cron' => 'id',
							'ftp_user' => 'ftp_user_id',
							'mail_domain' => 'domain_id',
							'mail_user' => 'mailuser_id',
							'mail_user_smtp' => 'mailuser_id',
							'mail_forwarding' => 'forwarding_id',
							'mail_get' => 'mailget_id',
							'openvz_vm' => 'vm_id',
							'shell_user' => 'shell_user_id',
							'webdav_user' => 'webdav_user_id',
							'web_database' => 'database_id',
							'web_domain' => 'domain_id',
							'web_folder' => 'web_folder_id',
							'web_folder_user' => 'web_folder_user_id'
							);
		$udata = $app->db->queryOneRecord('SELECT `userid` FROM `sys_user` WHERE `client_id` = ?', $client_id);
		$gdata = $app->db->queryOneRecord('SELECT `groupid` FROM `sys_group` WHERE `client_id` = ?', $client_id);
		$sys_groupid = $gdata['groupid'];
		$sys_userid = $udata['userid'];
		if($locked == 'y') {
			$prev_active = array();
			$prev_sysuser = array();
			foreach($to_disable as $current => $keycolumn) {
				$active_col = 'active';
				$reverse = false;
				if($current == 'mail_user') {
						$active_col = 'postfix';
				} elseif($current == 'mail_user_smtp') {
						$current = 'mail_user';
						$active_col = 'disablesmtp';
						$reverse = true;
				}

				if(!isset($prev_active[$current])) $prev_active[$current] = array();
				if(!isset($prev_sysuser[$current])) $prev_sysuser[$current] = array();

				$entries = $app->db->queryAllRecords('SELECT ?? as `id`, `sys_userid`, ?? FROM ?? WHERE `sys_groupid` = ?', $keycolumn, $active_col, $current, $sys_groupid);
				foreach($entries as $item) {

						if($item[$active_col] != 'y' && $reverse == false) $prev_active[$current][$item['id']][$active_col] = 'n';
						elseif($item[$active_col] == 'y' && $reverse == true) $prev_active[$current][$item['id']][$active_col] = 'y';
						if($item['sys_userid'] != $sys_userid) $prev_sysuser[$current][$item['id']] = $item['sys_userid'];
						// we don't have to store these if y, as everything without previous state gets enabled later

						//$app->db->datalogUpdate($current, array($active_col => ($reverse == true ? 'y' : 'n'), 'sys_userid' => $_SESSION["s"]["user"]["userid"]), $keycolumn, $item['id']);
						$app->db->datalogUpdate($current, array($active_col => ($reverse == true ? 'y' : 'n'), 'sys_userid' => $sys_userid), $keycolumn, $item['id']);
				}
			}

			$tmp_data['prev_active'] = $prev_active;
			$tmp_data['prev_sys_userid'] = $prev_sysuser;
			$app->db->query("UPDATE `client` SET `tmp_data` = ? WHERE `client_id` = ?", serialize($tmp_data), $client_id);
			unset($prev_active);
			unset($prev_sysuser);
		} elseif ($locked == 'n') {
			foreach($to_disable as $current => $keycolumn) {
				$active_col = 'active';
				$reverse = false;
				if($current == 'mail_user') {
						$active_col = 'postfix';
				} elseif($current == 'mail_user_smtp') {
						$current = 'mail_user';
						$active_col = 'disablesmtp';
						$reverse = true;
				}

				$entries = $app->db->queryAllRecords('SELECT ?? as `id` FROM ?? WHERE `sys_groupid` = ?', $keycolumn, $current, $sys_groupid);
				foreach($entries as $item) {
						$set_active = ($reverse == true ? 'n' : 'y');
						$set_inactive = ($reverse == true ? 'y' : 'n');
						$set_sysuser = $sys_userid;
						if(array_key_exists('prev_active', $tmp_data) == true
								&& array_key_exists($current, $tmp_data['prev_active']) == true
								&& array_key_exists($item['id'], $tmp_data['prev_active'][$current]) == true
								&& $tmp_data['prev_active'][$current][$item['id']][$active_col] == $set_inactive) $set_active = $set_inactive;
						if(array_key_exists('prev_sysuser', $tmp_data) == true
								&& array_key_exists($current, $tmp_data['prev_sysuser']) == true
								&& array_key_exists($item['id'], $tmp_data['prev_sysuser'][$current]) == true
								&& $tmp_data['prev_sysuser'][$current][$item['id']] != $sys_userid) $set_sysuser = $tmp_data['prev_sysuser'][$current][$item['id']];
						$app->db->datalogUpdate($current, array($active_col => $set_active, 'sys_userid' => $set_sysuser), $keycolumn, $item['id']);
				}
			}
			if(array_key_exists('prev_active', $tmp_data)) unset($tmp_data['prev_active']);
			$app->db->query("UPDATE `client` SET `tmp_data` = ? WHERE `client_id` = ?", serialize($tmp_data), $client_id);
		}
		unset($tmp_data);
		unset($entries);
		unset($to_disable);
    }
    // Function to cancel disable/enable a client
	public function func_client_cancel($client_id,$cancel) {
		global $app;
		if ($cancel == 'y') {
			$sql = "UPDATE sys_user SET active = '0' WHERE client_id = ?";
			$result = $app->db->query($sql, $client_id);
		} elseif($cancel == 'n') {
			$sql = "UPDATE sys_user SET active = '1' WHERE client_id = ?";
			$result = $app->db->query($sql, $client_id);
		} else {
			$result = false;
		}
		return $result;
	}	

    /**
     * Lookup a client's group + all groups he is reselling.
     *
     * @return string Comma separated list of groupid's
     */
    function clientid_to_groups_list($client_id) {
      global $app;

      if ($client_id != null) {
        // Get the clients groupid, and incase it's a reseller the groupid's of it's clients.
        $group = $app->db->queryOneRecord("SELECT GROUP_CONCAT(groupid) AS groups FROM `sys_group` WHERE client_id IN (SELECT client_id FROM `client` WHERE client_id=? OR parent_client_id=?)", $client_id, $client_id);
        return $group['groups'];
      }
      return null;
    }

}

?>
