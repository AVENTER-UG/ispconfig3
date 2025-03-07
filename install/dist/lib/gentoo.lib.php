<?php

/*
Copyright (c) 2007, Till Brehm, projektfarm Gmbh
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

class installer extends installer_base
{
	public function configure_jailkit()
	{
		global $conf;

		if (is_dir($conf['jailkit']['config_dir']))
		{
			$jkinit_content = $this->get_template_file($conf['jailkit']['jk_init'], true); //* get contents
			$this->write_config_file($conf['jailkit']['config_dir'] . '/' . $conf['jailkit']['jk_init'], $jkinit_content);

			$jkchroot_content = $this->get_template_file($conf['jailkit']['jk_chrootsh'], true); //* get contents
			$this->write_config_file($conf['jailkit']['config_dir'] . '/' . $conf['jailkit']['jk_chrootsh'], $jkchroot_content);
		}

		$command = 'chown root:root /var/www';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
	}

	public function configure_postfix($options = '') {
		global $conf,$autoinstall;

		$cf = $conf['postfix'];
		$config_dir = $cf['config_dir'];

		if(!is_dir($config_dir)){
			$this->error("The postfix configuration directory '$config_dir' does not exist.");
		}
    
    //* Get postfix version
		exec('postconf -d mail_version 2>&1', $out);
		$postfix_version = preg_replace('/.*=\s*/', '', $out[0]);
		unset($out);

		//* Install virtual mappings
		foreach (glob('tpl/mysql-virtual_*.master') as $filename) {
			$this->process_postfix_config( basename($filename, '.master') );
		}

		//* mysql-verify_recipients.cf
		$this->process_postfix_config('mysql-verify_recipients.cf');
    
    // test if lmtp if available
		$configure_lmtp = $this->get_postfix_service('lmtp','unix');

		//* postfix-dkim
		$filename='tag_as_originating.re';
		$full_file_name=$config_dir.'/'.$filename;
		if(is_file($full_file_name)) copy($full_file_name, $full_file_name.'~');
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/postfix-'.$filename.'.master', 'tpl/postfix-'.$filename.'.master');
		if($configure_lmtp) {
			$content = preg_replace('/amavis:/', 'lmtp:', $content);
		}
		wf($full_file_name, $content);
    
    $filename='tag_as_foreign.re';
		$full_file_name=$config_dir.'/'.$filename;
		if(is_file($full_file_name)) copy($full_file_name, $full_file_name.'~');
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/postfix-'.$filename.'.master', 'tpl/postfix-'.$filename.'.master');
		if($configure_lmtp) {
			$content = preg_replace('/amavis:/', 'lmtp:', $content);
		}
		wf($full_file_name, $content);    
    
		//* Changing mode and group of the new created config files.
		/*caselog('chmod o= '.$config_dir.'/mysql-virtual_*.cf* &> /dev/null',
			__FILE__, __LINE__, 'chmod on mysql-virtual_*.cf*', 'chmod on mysql-virtual_*.cf* failed');
		caselog('chgrp '.$cf['group'].' '.$config_dir.'/mysql-virtual_*.cf* &> /dev/null',
			__FILE__, __LINE__, 'chgrp on mysql-virtual_*.cf*', 'chgrp on mysql-virtual_*.cf* failed');*/

		//* Creating virtual mail user and group
		$command = 'groupadd -g '.$cf['vmail_groupid'].' '.$cf['vmail_groupname'];
		if(!is_group($cf['vmail_groupname'])) caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$command = 'useradd -g '.$cf['vmail_groupname'].' -u '.$cf['vmail_userid'].' '.$cf['vmail_username'].' -d '.$cf['vmail_mailbox_base'].' -m';
		if(!is_user($cf['vmail_username'])) caselog("$command &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* These postconf commands will be executed on installation and update
		$server_ini_rec = $this->db->queryOneRecord("SELECT config FROM ?? WHERE server_id = ?", $conf["mysql"]["database"].'.server', $conf['server_id']);
		$server_ini_array = ini_to_array(stripslashes($server_ini_rec['config']));
		unset($server_ini_rec);

    //* If there are RBL's defined, format the list and add them to smtp_recipient_restrictions to prevent removal after an update
		$rbl_list = '';
		if (@isset($server_ini_array['mail']['realtime_blackhole_list']) && $server_ini_array['mail']['realtime_blackhole_list'] != '') {
			$rbl_hosts = explode(",", str_replace(" ", "", $server_ini_array['mail']['realtime_blackhole_list']));
			foreach ($rbl_hosts as $key => $value) {
				$rbl_list .= ", reject_rbl_client ". $value;
			}
		}
		unset($rbl_hosts);

		//* If Postgrey is installed, configure it
		$greylisting = '';
		if($conf['postgrey']['installed'] == true) {
			$greylisting = ', check_recipient_access mysql:/etc/postfix/mysql-virtual_policy_greylist.cf';
		}

		$reject_sender_login_mismatch = '';
		$reject_authenticated_sender_login_mismatch = '';
		if(isset($server_ini_array['mail']['reject_sender_login_mismatch']) && ($server_ini_array['mail']['reject_sender_login_mismatch'] == 'y')) {
      $reject_sender_login_mismatch = ',reject_sender_login_mismatch,';
			$reject_authenticated_sender_login_mismatch = 'reject_authenticated_sender_login_mismatch, ';
		}

		# placeholder includes comment char
		$stress_adaptive_placeholder = '#{stress_adaptive} ';
		$stress_adaptive = (isset($server_ini_array['mail']['stress_adaptive']) && ($server_ini_array['mail']['stress_adaptive'] == 'y')) ? '' : $stress_adaptive_placeholder;

		$reject_unknown_client_hostname='';
		if (isset($server_ini_array['mail']['reject_unknown']) && ($server_ini_array['mail']['reject_unknown'] == 'client' || $server_ini_array['mail']['reject_unknown'] == 'client_helo')) {
			$reject_unknown_client_hostname=',reject_unknown_client_hostname';
		}
		$reject_unknown_helo_hostname='';
		if ((!isset($server_ini_array['mail']['reject_unknown'])) || $server_ini_array['mail']['reject_unknown'] == 'helo' || $server_ini_array['mail']['reject_unknown'] == 'client_helo') {
			$reject_unknown_helo_hostname=',reject_unknown_helo_hostname';
		}

		unset($server_ini_array);

		$myhostname = str_replace('.','\.',$conf['hostname']);

		$postconf_placeholders = array('{config_dir}' => $config_dir,
			'{vmail_mailbox_base}' => $cf['vmail_mailbox_base'],
			'{vmail_userid}' => $cf['vmail_userid'],
			'{vmail_groupid}' => $cf['vmail_groupid'],
			'{rbl_list}' => $rbl_list,
			'{greylisting}' => $greylisting,
			'{reject_slm}' => $reject_sender_login_mismatch,
			'{reject_aslm}' => $reject_authenticated_sender_login_mismatch,
			'{myhostname}' => $myhostname,
			$stress_adaptive_placeholder => $stress_adaptive,
			'{reject_unknown_client_hostname}' => $reject_unknown_client_hostname,
			'{reject_unknown_helo_hostname}' => $reject_unknown_helo_hostname,
		);

		$postconf_tpl = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/gentoo_postfix.conf.master', 'tpl/gentoo_postfix.conf.master');
		$postconf_tpl = strtr($postconf_tpl, $postconf_placeholders);
		$postconf_commands = array_filter(explode("\n", $postconf_tpl)); // read and remove empty lines
        
    //* Merge version-specific postfix config
		if(version_compare($postfix_version , '2.5', '>=')) {
		    $configfile = 'postfix_2-5.conf';
		    $content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		    $content = strtr($content, $postconf_placeholders);
		    $postconf_commands = array_merge($postconf_commands, array_filter(explode("\n", $content)));
		}
		if(version_compare($postfix_version , '2.10', '>=')) {
		    $configfile = 'postfix_2-10.conf';
		    $content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		    $content = strtr($content, $postconf_placeholders);
		    $postconf_commands = array_merge($postconf_commands, array_filter(explode("\n", $content)));
		}
		if(version_compare($postfix_version , '3.0', '>=')) {
		    $configfile = 'postfix_3-0.conf';
		    $content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		    $content = strtr($content, $postconf_placeholders);
		    $postconf_commands = array_merge($postconf_commands, array_filter(explode("\n", $content)));
		}
		if(version_compare($postfix_version , '3.3', '>=')) {
		    $configfile = 'postfix_3-3.conf';
		    $content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		    $content = strtr($content, $postconf_placeholders);
		    $postconf_commands = array_merge($postconf_commands, array_filter(explode("\n", $content)));
		}
		$configfile = 'postfix_custom.conf';
		if(file_exists($conf['ispconfig_install_dir'].'/server/conf-custom/install/' . $configfile . '.master')) {
			$content = file_get_contents($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master');
			$content = strtr($content, $postconf_placeholders);
			$postconf_commands = array_merge($postconf_commands, array_filter(explode("\n", $content)));
		}

		// Remove comment lines, these would give fatal errors when passed to postconf.
		$postconf_commands = array_filter($postconf_commands, function($line) { return preg_match('/^[^#]/', $line); });
    
		//* These postconf commands will be executed on installation only
		if($this->is_update == false) {
			$postconf_commands = array_merge($postconf_commands, array(
					'myhostname = '.$conf['hostname'],
					'mydestination = '.$conf['hostname'].', localhost, localhost.localdomain',
					'mynetworks = 127.0.0.0/8 [::1]/128'
				));
		}

		//* Create the header and body check files
		touch($config_dir.'/header_checks');
		touch($config_dir.'/mime_header_checks');
		touch($config_dir.'/nested_header_checks');
		touch($config_dir.'/body_checks');
		touch($config_dir.'/sasl_passwd');
    
    //* Create the mailman files
		if(!is_dir('/var/lib/mailman/data')) exec('mkdir -p /var/lib/mailman/data');
		if(!is_file('/var/lib/mailman/data/aliases')) touch('/var/lib/mailman/data/aliases');
		exec('postalias /var/lib/mailman/data/aliases');
		if(!is_file('/var/lib/mailman/data/virtual-mailman')) touch('/var/lib/mailman/data/virtual-mailman');
		exec('postmap /var/lib/mailman/data/virtual-mailman');
		if(!is_file('/var/lib/mailman/data/transport-mailman')) touch('/var/lib/mailman/data/transport-mailman');
		exec('/usr/sbin/postmap /var/lib/mailman/data/transport-mailman');

		//* Create auxillary postfix conf files
		$configfile = 'helo_access';
		if(is_file($config_dir.'/'.$configfile)) {
			copy($config_dir.'/'.$configfile, $config_dir.'/'.$configfile.'~');
			chmod($config_dir.'/'.$configfile.'~', 0400);
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		$content = strtr($content, $postconf_placeholders);
		# todo: look up this server's ip addrs and loop through each
		# todo: look up domains hosted on this server and loop through each
		wf($config_dir.'/'.$configfile, $content);

		$configfile = 'blacklist_helo';
		if(is_file($config_dir.'/'.$configfile)) {
			copy($config_dir.'/'.$configfile, $config_dir.'/'.$configfile.'~');
			chmod($config_dir.'/'.$configfile.'~', 0400);
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		$content = strtr($content, $postconf_placeholders);
		wf($config_dir.'/'.$configfile, $content);

		//* Make a backup copy of the main.cf file
		copy($config_dir.'/main.cf', $config_dir.'/main.cf~');

		//* Executing the postconf commands
		foreach($postconf_commands as $cmd) {
			$command = "postconf -e '$cmd'";
      swriteln($command);
      caselog($command." &> /dev/null", __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);
		}
		
		if (!stristr($options, 'dont-create-certs')){
			//* Create the SSL certificate
      if(AUTOINSTALL){
				$command = 'cd '.$config_dir.'; '
					."openssl req -new -subj '/C=".escapeshellcmd($autoinstall['ssl_cert_country'])."/ST=".escapeshellcmd($autoinstall['ssl_cert_state'])."/L=".escapeshellcmd($autoinstall['ssl_cert_locality'])."/O=".escapeshellcmd($autoinstall['ssl_cert_organisation'])."/OU=".escapeshellcmd($autoinstall['ssl_cert_organisation_unit'])."/CN=".escapeshellcmd($autoinstall['ssl_cert_common_name'])."' -outform PEM -out smtpd.cert -newkey rsa:4096 -nodes -keyout smtpd.key -keyform PEM -days 3650 -x509";
			} else {
				$command = 'cd '.$config_dir.'; '
					.'openssl req -new -outform PEM -out smtpd.cert -newkey rsa:4096 -nodes -keyout smtpd.key -keyform PEM -days 3650 -x509';
			}
			exec($command);

			$command = 'chmod o= '.$config_dir.'/smtpd.key';
			caselog($command.' &> /dev/null', __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);
		}

		//** We have to change the permissions of the courier authdaemon directory to make it accessible for maildrop.
		$command = 'chmod 755  /var/run/courier/authdaemon/';
		if(is_file('/var/run/courier/authdaemon/')) caselog($command.' &> /dev/null', __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);

		//* Check maildrop service in posfix master.cf
		$quoted_regex = '^maildrop   unix.*pipe flags=DRhu user=vmail '.preg_quote('argv=/usr/bin/maildrop -d '.$cf['vmail_username'].' ${extension} ${recipient} ${user} ${nexthop} ${sender}', '/');
		$configfile = $config_dir.'/master.cf';
		if($this->get_postfix_service('maildrop', 'unix')) {
			exec ("postconf -M maildrop.unix 2> /dev/null", $out, $ret);
			$change_maildrop_flags = @(preg_match("/$quoted_regex/", $out[0]) && $out[0] !='')?false:true;
		} else {
			$change_maildrop_flags = @(preg_match("/$quoted_regex/", $configfile))?false:true;
		}
		if ($change_maildrop_flags) {
			//* Change maildrop service in posfix master.cf
			if(is_file($config_dir.'/master.cf')) {
				copy($config_dir.'/master.cf', $config_dir.'/master.cf~');
			}
			if(is_file($config_dir.'/master.cf~')) {
				chmod($config_dir.'/master.cf~', 0400);
 			}
			$configfile = $config_dir.'/master.cf';
			$content = rf($configfile);
			$content =	str_replace('flags=DRhu user=vmail argv=/usr/bin/maildrop -d ${recipient}',
						'flags=DRhu user='.$cf['vmail_username'].' argv=/usr/bin/maildrop -d '.$cf['vmail_username'].' ${extension} ${recipient} ${user} ${nexthop} ${sender}',
						$content);
			wf($configfile, $content);
		}
    
    //* Writing the Maildrop mailfilter file
		$configfile = 'mailfilter';
		if(is_file($cf['vmail_mailbox_base'].'/.'.$configfile)) {
			copy($cf['vmail_mailbox_base'].'/.'.$configfile, $cf['vmail_mailbox_base'].'/.'.$configfile.'~');
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		$content = str_replace('{dist_postfix_vmail_mailbox_base}', $cf['vmail_mailbox_base'], $content);
		wf($cf['vmail_mailbox_base'].'/.'.$configfile, $content);     

		//* Create the directory for the custom mailfilters
		if(!is_dir($cf['vmail_mailbox_base'].'/mailfilters')) {
			$command = 'mkdir '.$cf['vmail_mailbox_base'].'/mailfilters';
			caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}	

		//* Chmod and chown the .mailfilter file
		$command = 'chown '.$cf['vmail_username'].':'.$cf['vmail_groupname'].' '.$cf['vmail_mailbox_base'].'/.mailfilter';
		caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$command = 'chmod 600 '.$cf['vmail_mailbox_base'].'/.mailfilter';
		caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

	}
  
	public function configure_saslauthd()
	{
		global $conf;

		$content = $this->get_template_file('sasl_smtpd.conf', true, true); //* get contents & insert db cred
		$this->write_config_file($conf['saslauthd']['config_dir'].'/smtpd.conf', $content);

		//* Edit the file saslauthd config file
		$content = rf($conf['saslauthd']['config_file']);
		$content = preg_replace('/(?<=\n)SASLAUTHD_OPTS="\$\{SASLAUTHD_OPTS\}[^"]+"/', 'SASLAUTHD_OPTS="${SASLAUTHD_OPTS} -a pam -r -c -s 128 -t 30 -n 5"', $content);

		$this->write_config_file($conf['saslauthd']['config_file'], $content);
	}

	public function configure_courier()
	{
		global $conf;

		//* authmysqlrc
		$content = $this->get_template_file('authmysqlrc', true, true); //* get contents & insert db cred
		$this->write_config_file($conf['courier']['config_dir'].'/authmysqlrc', $content);

		//* authdaemonrc
		$configfile = $conf['courier']['config_dir'].'/authdaemonrc';

		$content = rf($configfile);
		$content = preg_replace('/(?<=\n)authmodulelist="[^"]+"/', "authmodulelist=\"authmysql\"", $content);
		$this->write_config_file($configfile, $content);

		//* create certificates
		$command = 'mkimapdcert';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$command = 'mkpop3dcert';
		caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
	}

	public function configure_dovecot() {
		global $conf;

		$virtual_transport = 'dovecot';

		$configure_lmtp = false;

		// use lmtp if installed
		if($configure_lmtp = (is_file('/usr/lib/dovecot/lmtp') || is_file('/usr/libexec/dovecot/lmtp'))) {
			$virtual_transport = 'lmtp:unix:private/dovecot-lmtp';
		}

		// check if virtual_transport must be changed
		if ($this->is_update) {
			$tmp = $this->db->queryOneRecord("SELECT * FROM ?? WHERE server_id = ?", $conf["mysql"]["database"] . ".server", $conf['server_id']);
			$ini_array = ini_to_array(stripslashes($tmp['config']));
			// ini_array needs not to be checked, because already done in update.php -> updateDbAndIni()

			if(isset($ini_array['mail']['mailbox_virtual_uidgid_maps']) && $ini_array['mail']['mailbox_virtual_uidgid_maps'] == 'y') {
				$virtual_transport = 'lmtp:unix:private/dovecot-lmtp';
				$configure_lmtp = true;
			}
		}

		$config_dir = $conf['postfix']['config_dir'];
		$quoted_config_dir = preg_quote($config_dir, '|');
		$postfix_version = `postconf -d mail_version 2>/dev/null`;
		$postfix_version = preg_replace( '/mail_version\s*=\s*(.*)\s*/', '$1', $postfix_version );

		//* Configure master.cf and add a line for deliver
		if(!$this->get_postfix_service('dovecot', 'unix')) {
 			//* backup
			if(is_file($config_dir.'/master.cf')){
				copy($config_dir.'/master.cf', $config_dir.'/master.cf~2');
			}
			if(is_file($config_dir.'/master.cf~2')){
				chmod($config_dir.'/master.cf~2', 0400);
			}
			//* Configure master.cf and add a line for deliver
			$content = rf($config_dir.'/master.cf');
			$deliver_content = 'dovecot   unix  -       n       n       -       -       pipe'."\n".'  flags=DRhu user=vmail:vmail argv=/usr/lib/dovecot/deliver -f ${sender} -d ${user}@${nexthop}'."\n";
			af($config_dir.'/master.cf', $deliver_content);
			unset($content);
			unset($deliver_content);
		}

		//* Reconfigure postfix to use dovecot authentication
		// Adding the amavisd commands to the postfix configuration
		$postconf_commands = array (
			'dovecot_destination_recipient_limit = 1',
			'virtual_transport = '.$virtual_transport,
			'smtpd_sasl_type = dovecot',
			'smtpd_sasl_path = private/auth'
		);

		// Make a backup copy of the main.cf file
		copy($config_dir.'/main.cf', $config_dir.'/main.cf~3');

		$options = preg_split("/,\s*/", exec("postconf -h smtpd_recipient_restrictions"));
		$new_options = array();
		foreach ($options as $value) {
			$value = trim($value);
			if ($value == '') continue;
			if (preg_match("|check_recipient_access\s+proxy:mysql:${quoted_config_dir}/mysql-verify_recipients.cf|", $value)) {
				continue;
			}
			$new_options[] = $value;
		}
		if ($configure_lmtp && $conf['mail']['content_filter'] === 'amavisd') {
			for ($i = 0; isset($new_options[$i]); $i++) {
				if ($new_options[$i] == 'reject_unlisted_recipient') {
					array_splice($new_options, $i+1, 0, array("check_recipient_access proxy:mysql:${config_dir}/mysql-verify_recipients.cf"));
					break;
				}
			}
			# postfix < 3.3 needs this when using reject_unverified_recipient:
			if(version_compare($postfix_version, 3.3, '<')) {
				$postconf_commands[] = "enable_original_recipient = yes";
			}
		}
		$postconf_commands[] = "smtpd_recipient_restrictions = ".implode(", ", $new_options);

		// Executing the postconf commands
		foreach($postconf_commands as $cmd) {
			$command = "postconf -e '$cmd'";
			caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}

		//* backup dovecot.conf
		$config_dir = $conf['dovecot']['config_dir'];
		$configfile = 'dovecot.conf';
		if(is_file($config_dir.'/'.$configfile)) {
			copy($config_dir.'/'.$configfile, $config_dir.'/'.$configfile.'~');
		}

		//* Get the dovecot version
		exec('dovecot --version', $tmp);
		$dovecot_version = $tmp[0];
		unset($tmp);

		//* Copy dovecot configuration file
		if(version_compare($dovecot_version,1, '<=')) {	//* Dovecot 1.x
			if(is_file($conf['ispconfig_install_dir'].'/server/conf-custom/install/gentoo_dovecot.conf.master')) {
				copy($conf['ispconfig_install_dir'].'/server/conf-custom/install/gentoo_dovecot.conf.master', $config_dir.'/'.$configfile);
			} else {
				copy('dist/tpl/gentoo/dovecot.conf.master', $config_dir.'/'.$configfile);
			}
		} else {	//* Dovecot 2.x
			if(is_file($conf['ispconfig_install_dir'].'/server/conf-custom/install/gentoo_dovecot2.conf.master')) {
				copy($conf['ispconfig_install_dir'].'/server/conf-custom/install/gentoo_dovecot2.conf.master', $config_dir.'/'.$configfile);
			} else {
				copy('dist/tpl/gentoo/dovecot2.conf.master', $config_dir.'/'.$configfile);
			}
			// Copy custom config file
			if(is_file($conf['ispconfig_install_dir'].'/server/conf-custom/install/dovecot_custom.conf.master')) {
				if(!@is_dir($config_dir . '/conf.d')) {
					mkdir($config_dir . '/conf.d');
				}
				copy($conf['ispconfig_install_dir'].'/server/conf-custom/install/dovecot_custom.conf.master', $config_dir.'/conf.d/99-ispconfig-custom-config.conf');
			}
			replaceLine($config_dir.'/'.$configfile, 'postmaster_address = postmaster@example.com', 'postmaster_address = postmaster@'.$conf['hostname'], 1, 0);
			replaceLine($config_dir.'/'.$configfile, 'postmaster_address = webmaster@localhost', 'postmaster_address = postmaster@'.$conf['hostname'], 1, 0);
			if(version_compare($dovecot_version, 2.1, '<')) {
				removeLine($config_dir.'/'.$configfile, 'ssl_protocols =');
			}
			if(version_compare($dovecot_version,2.2) >= 0) {
				// Dovecot > 2.2 does not recognize !SSLv2 anymore on Debian 9
				$content = file_get_contents($config_dir.'/'.$configfile);
				$content = str_replace('!SSLv2','',$content);
				file_put_contents($config_dir.'/'.$configfile,$content);
				unset($content);
			}
			if(version_compare($dovecot_version,2.3) >= 0) {
				// Remove deprecated setting(s)
				removeLine($config_dir.'/'.$configfile, 'ssl_protocols =');

				// Check if we have a dhparams file and if not, create it
				if(!file_exists('/etc/dovecot/dh.pem')) {
					swriteln('Creating new DHParams file, this takes several minutes. Do not interrupt the script.');
					if(file_exists('/var/lib/dovecot/ssl-parameters.dat')) {
						// convert existing ssl parameters file
						$command = 'dd if=/var/lib/dovecot/ssl-parameters.dat bs=1 skip=88 | openssl dhparam -inform der > /etc/dovecot/dh.pem';
						caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
					} else {
						/*
						   Create a new dhparams file. We use 2048 bit only as it simply takes too long
						   on smaller systems to generate a 4096 bit dh file (> 30 minutes). If you need
						   a 4096 bit file, create it manually before you install ISPConfig
						*/
						$command = 'openssl dhparam -out /etc/dovecot/dh.pem 2048';
						caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
					}
				}
				//remove #2.3+ comment
				$content = file_get_contents($config_dir.'/'.$configfile);
				$content = str_replace('#2.3+ ','',$content);
				file_put_contents($config_dir.'/'.$configfile,$content);
				unset($content);

			} else {
				// remove settings which are not supported in Dovecot < 2.3
				removeLine($config_dir.'/'.$configfile, 'ssl_min_protocol =');
				removeLine($config_dir.'/'.$configfile, 'ssl_dh =');
			}
		}

		$dovecot_protocols = 'imap pop3';

		//* dovecot-lmtpd
		if($configure_lmtp) {
			$dovecot_protocols .= ' lmtp';
		}

		//* dovecot-managesieved
		if(is_file('/usr/lib/dovecot/managesieve') || is_file('/usr/libexec/dovecot/managesieve')) {
			$dovecot_protocols .= ' sieve';
		}

		replaceLine($config_dir.'/'.$configfile, 'protocols = imap pop3', "protocols = $dovecot_protocols", 1, 0);

		//* dovecot-sql.conf
		$configfile = 'dovecot-sql.conf';
		if(is_file($config_dir.'/'.$configfile)) {
			copy($config_dir.'/'.$configfile, $config_dir.'/'.$configfile.'~');
		}
		if(is_file($config_dir.'/'.$configfile.'~')) chmod($config_dir.'/'.$configfile.'~', 0400);
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/debian_dovecot-sql.conf.master', 'tpl/debian_dovecot-sql.conf.master');
		$content = str_replace('{mysql_server_ispconfig_user}', $conf['mysql']['ispconfig_user'], $content);
		$content = str_replace('{mysql_server_ispconfig_password}', $conf['mysql']['ispconfig_password'], $content);
		$content = str_replace('{mysql_server_database}', $conf['mysql']['database'], $content);
		$content = str_replace('{mysql_server_host}', $conf['mysql']['host'], $content);
		$content = str_replace('{mysql_server_port}', $conf['mysql']['port'], $content);
		$content = str_replace('{server_id}', $conf['server_id'], $content);
		# enable iterate_query for dovecot2
		if(version_compare($dovecot_version,2, '>=')) {
			$content = str_replace('# iterate_query', 'iterate_query', $content);
		}
		wf($config_dir.'/'.$configfile, $content);

		chmod($config_dir.'/'.$configfile, 0600);
		chown($config_dir.'/'.$configfile, 'root');
		chgrp($config_dir.'/'.$configfile, 'root');

		// Dovecot shall ignore mounts in website directory
		if(is_installed('doveadm')) exec("doveadm mount add '/var/www/*' ignore > /dev/null 2> /dev/null");

	}

	public function configure_spamassassin()
	{
		return true;
	}

	public function configure_getmail()
	{
		global $conf;

		$config_dir = $conf['getmail']['config_dir'];

		if (!is_dir($config_dir)) {
			exec('mkdir -p '.escapeshellcmd($config_dir));
		}

		$command = "useradd -d $config_dir ".$conf['getmail']['user'];
		if (!is_user('getmail')) {
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}

		$command = "chown -R getmail $config_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$command = "chmod -R 700 $config_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Getmail will be run from cron. In order to have access to cron the getmail user needs to be part of the cron group.
		$command = "gpasswd -a getmail " . $conf['cron']['group'];
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
	}

	public function configure_amavis()
	{
		global $conf;

		//* Amavisd-new user config file
		$conf_file = 'amavisd-ispconfig.conf';
		$conf_path = dirname($conf['amavis']['config_file']) . '/' . $conf_file;

		$content = $this->get_template_file($conf_file, true, true); //* get contents & insert db cred
		$this->write_config_file($conf_path, $content);

		//* Activate config directory in default file
		$amavis_conf = rf($conf['amavis']['config_file']);
		if (stripos($amavis_conf, $conf_path) === false)
		{
			$amavis_conf = preg_replace('/^(1;.*)$/m', "include_config_files('$conf_path');\n$1", $amavis_conf);
			$this->write_config_file($conf['amavis']['config_file'], $amavis_conf);
		}

		//* Adding the amavisd commands to the postfix configuration
		$postconf_commands = array (
			'content_filter = amavis:[127.0.0.1]:10024',
			'receive_override_options = no_address_mappings'
		);

		foreach($postconf_commands as $cmd) {
			$command = "postconf -e '$cmd'";
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}

		$config_dir = $conf['postfix']['config_dir'];

		// Adding amavis-services to the master.cf file

		// backup master.cf
		if(is_file($config_dir.'/master.cf')) copy($config_dir.'/master.cf', $config_dir.'/master.cf~');

		// first remove the old service definitions
		$this->remove_postfix_service('amavis','unix');
		$this->remove_postfix_service('127.0.0.1:10025','inet');
		$this->remove_postfix_service('127.0.0.1:10027','inet');

		// then add them back
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/master_cf_amavis.master', 'tpl/master_cf_amavis.master');
		af($config_dir.'/master.cf', $content);
		unset($content);

		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/master_cf_amavis10025.master', 'tpl/master_cf_amavis10025.master');
		af($config_dir.'/master.cf', $content);
		unset($content);

		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/master_cf_amavis10027.master', 'tpl/master_cf_amavis10027.master');
		af($config_dir.'/master.cf', $content);
		unset($content);

		//* Add the clamav user to the amavis group
		exec('usermod -a -G amavis clamav');
	}

	public function configure_pureftpd()
	{
		global $conf;

		//* configure pure-ftpd for MySQL authentication against the ispconfig database
		$content = $this->get_template_file('pureftpd_mysql.conf', true, true); //* get contents & insert db cred
		$content = str_replace('{server_id}', $conf['server_id'], $content);

		$this->write_config_file($conf['pureftpd']['mysql_config_file'], $content, 600, 'root', 'root');

		//* enable pure-ftpd and server settings
		$content = rf($conf["pureftpd"]["config_file"]);

		$content = preg_replace('/#?IS_CONFIGURED="(?:yes|no)"/', 'IS_CONFIGURED="yes"', $content);
		$content = str_replace('AUTH="-l unix"', 'AUTH="-l mysql:'.$conf['pureftpd']['mysql_config_file'].'"', $content);

		//* Logging defaults to syslog's ftp facility. Override this behaviour for better compatibility with debian/ubuntu
		//* and specify the format.
		$logdir = '/var/log/pure-ftpd';
		if (!is_dir($logdir)) {
			mkdir($logdir, 0755, true);
		}

		/**
		 * @link http://download.pureftpd.org/pub/pure-ftpd/doc/README
		 * -b brokenclientscompatibility
		 * -A chrooteveryone
		 * -E noanonymous
		 * -O altlog <format>:<log file>
		 * -Z customerproof (Add safe guards against common customer mistakes ie. like chmod 0 on their own files)
		 * -D displaydotfiles
		 * -H dontresolve
		 */


		 //* Enable TLS if certificate file exists
		 $enable_tls = '';
		 if(file_exists('/etc/ssl/private/pure-ftpd.pem')) {
			 $enable_tls = ' -Y 1';
		 }

		 $content = preg_replace('/MISC_OTHER="[^"]+"/', 'MISC_OTHER="-b -A -E -Z -D -H -O clf:'.$logdir.'/transfer.log'.$enable_tls.'"', $content);

		$this->write_config_file($conf['pureftpd']['config_file'], $content);
    
    //* Since version 1.0.50: Configuration through /etc/conf.d/pure-ftpd is now deprecated!    
    exec("/usr/sbin/pure-ftpd --help | head -1",$out);
    if(preg_match("#v([0-9\.]+)\s#",$out[0],$matches)){
      $pureftpd_version = $matches[1];
      
      if(version_compare($pureftpd_version, '1.0.50', '>=')) { 
        $configfile = $conf['pureftpd']['main_config_file'];
    		if(is_file($configfile)) {
    			copy($configfile, $configfile.'~');
    		}
    		
        $content = rf($configfile);
        $content = preg_replace('/BrokenClientsCompatibility\s+(yes|no)/', 'BrokenClientsCompatibility   yes', $content);
        $content = preg_replace('/ChrootEveryone\s+(yes|no)/', 'ChrootEveryone               yes', $content);
        $content = preg_replace('/NoAnonymous\s+(yes|no)/', 'NoAnonymous                  yes', $content);
        $content = preg_replace('/#? AltLog\s+clf.*\s/', 'AltLog                       clf:/var/log/pureftpd.log', $content);
        $content = preg_replace('/CustomerProof\s+(yes|no)/', 'CustomerProof                yes', $content);
        $content = preg_replace('/DisplayDotFiles\s+(yes|no)/', 'DisplayDotFiles              yes', $content);
        $content = preg_replace('/DontResolve\s+(yes|no)/', 'DontResolve                  yes', $content);
        $content = preg_replace('/#? MySQLConfigFile\s+\/.*\s/', 'MySQLConfigFile              ' . $conf['pureftpd']['mysql_config_file'], $content);
        
        if(file_exists('/etc/ssl/private/pure-ftpd.pem')) {
          $content = preg_replace('/(#?) TLS\s+(0|1)/', 'TLS                          1', $content);
        }
        
        wf($configfile, $content);
      }
    }
    
	}

	public function configure_powerdns()
	{
		global $conf;

		//* Create the database
		if(!$this->db->query('CREATE DATABASE IF NOT EXISTS ?? DEFAULT CHARACTER SET ?', $conf['powerdns']['database'], $conf['mysql']['charset'])) {
			$this->error('Unable to create MySQL database: '.$conf['powerdns']['database'].'.');
		}

		//* Create the ISPConfig database user in the local database
		$query = 'GRANT ALL ON ??.* TO ?@?';
		if(!$this->db->query($query, $conf['powerdns']['database'], $conf['mysql']['ispconfig_user'], 'localhost')) {
			$this->error('Unable to create user for powerdns database Error: '.$this->db->errorMessage);
		}

		//* load the powerdns databse dump
		if($conf['mysql']['admin_password'] == '') {
			caselog("mysql --default-character-set=".$conf['mysql']['charset']." -h '".$conf['mysql']['host']."' -u '".$conf['mysql']['admin_user']."' --force '".$conf['powerdns']['database']."' < '".ISPC_INSTALL_ROOT."/install/sql/powerdns.sql' &> /dev/null",
				__FILE__, __LINE__, 'read in ispconfig3.sql', 'could not read in powerdns.sql');
		} else {
			caselog("mysql --default-character-set=".$conf['mysql']['charset']." -h '".$conf['mysql']['host']."' -u '".$conf['mysql']['admin_user']."' -p'".$conf['mysql']['admin_password']."' --force '".$conf['powerdns']['database']."' < '".ISPC_INSTALL_ROOT."/install/sql/powerdns.sql' &> /dev/null",
				__FILE__, __LINE__, 'read in ispconfig3.sql', 'could not read in powerdns.sql');
		}

		//* Create the powerdns config file
		$content = $this->get_template_file('pdns.local', true, true); //* get contents & insert db cred
		$content = str_replace('{powerdns_database}', $conf['powerdns']['database'], $content);

		$this->write_config_file($conf["powerdns"]["config_dir"].'/'.$conf["powerdns"]["config_file"], $content, 600, 'root', 'root');

		//* Create symlink to init script to start the correct config file
		if( !is_link($conf['init_scripts'].'/'.$conf['powerdns']['init_script']) ) {
			symlink($conf['init_scripts'].'/pdns', $conf['init_scripts'].'/'.$conf['powerdns']['init_script']);
		}
	}

	public function configure_bind() {
		global $conf;

		//* Check if the zonefile directory has a slash at the end
		$content=$conf['bind']['bind_zonefiles_dir'];
		if(substr($content, -1, 1) != '/') {
			$content .= '/';
		}

		//* New default format of named.conf uses views. Check which version the system is using and include our zones file.
		$named_conf = rf($conf['bind']['named_conf_path']);
		if (stripos($named_conf, 'include "'.$conf['bind']['named_conf_local_path'].'";') === false)
		{
			preg_match_all("/(?<=\n)view \"(?:public|internal)\" in \{.*\n\};/Us", $named_conf, $views);
			if (count($views[0]) == 2) {
				foreach ($views[0] as $view) {
					$named_conf = str_replace($view, substr($view, 0, -2)."include \"{$conf['bind']['named_conf_local_path']}\";\n};", $named_conf);
				}

				wf($conf['bind']['named_conf_path'], $named_conf);
			}
			else {
				af($conf['bind']['named_conf_path'], 'include "'.$conf['bind']['named_conf_local_path'].'";');
			}
		}
	}

	public function configure_apache()
	{
		global $conf;

		if($conf['apache']['installed'] == false) return;
		//* Create the logging directory for the vhost logfiles
		if (!is_dir($conf['ispconfig_log_dir'].'/httpd')) {
			mkdir($conf['ispconfig_log_dir'].'/httpd', 0755, true);
		}

		if (is_file($conf['suphp']['config_file']))
		{
			$content = rf($conf['suphp']['config_file']);

			if (!preg_match('|^x-httpd-suphp=php:/usr/bin/php-cgi$|m', $content))
			{
				$content = preg_replace('/;Handler for php-scripts/', ";Handler for php-scripts\nx-httpd-suphp=php:/usr/bin/php-cgi", $content);
				$content = preg_replace('/;?umask=\d+/', 'umask=0022', $content);
			}

			$this->write_config_file($conf['suphp']['config_file'], $content);
		}

		//* Enable ISPConfig default vhost settings
		$default_vhost_path = $conf['apache']['vhost_conf_dir'].'/'.$conf['apache']['vhost_default'];
		if (is_file($default_vhost_path))
		{
			$content = rf($default_vhost_path);

			$content = preg_replace('/^#?\s*NameVirtualHost.*$/m', 'NameVirtualHost *:80', $content);
			$content = preg_replace('/<VirtualHost[^>]+>/', '<VirtualHost *:80>', $content);

			$this->write_config_file($default_vhost_path, $content);
		}

		//* Generate default ssl certificates
		if (!is_dir($conf['apache']['ssl_dir'])) {
			mkdir($conf['apache']['ssl_dir']);
		}

		if ($conf['services']['mail'] == true)
		{
			copy($conf['postfix']['config_dir']."/smtpd.key", $conf['apache']['ssl_dir']."/server.key");
			copy($conf['postfix']['config_dir']."/smtpd.cert", $conf['apache']['ssl_dir']."/server.crt");
		}
		else
		{
			if (!is_file($conf['apache']['ssl_dir'] . '/server.crt')) {
				exec("openssl req -new -outform PEM -out {$conf['apache']['ssl_dir']}/server.crt -newkey rsa:2048 -nodes -keyout {$conf['apache']['ssl_dir']}/server.key -keyform PEM -days 365 -x509");
			}
		}



		//* Copy the ISPConfig configuration include
		$tpl = new tpl('apache_ispconfig.conf.master');
		$tpl->setVar('apache_version',getapacheversion());

		if($this->is_update == true) {
			$tpl->setVar('logging',get_logging_state());
		} else {
			$tpl->setVar('logging','yes');
		}

		$records = $this->db->queryAllRecords("SELECT * FROM ?? WHERE server_id = ? AND virtualhost = 'y'", $conf['mysql']['master_database'] . '.server_ip', $conf['server_id']);
		$ip_addresses = array();

		if(is_array($records) && count($records) > 0) {
			foreach($records as $rec) {
				if($rec['ip_type'] == 'IPv6') {
					$ip_address = '['.$rec['ip_address'].']';
				} else {
					$ip_address = $rec['ip_address'];
				}
				$ports = explode(',', $rec['virtualhost_port']);
				if(is_array($ports)) {
					foreach($ports as $port) {
						$port = intval($port);
						if($port > 0 && $port < 65536 && $ip_address != '') {
							$ip_addresses[] = array('ip_address' => $ip_address, 'port' => $port);
						}
					}
				}
			}
		}

		if(count($ip_addresses) > 0) $tpl->setLoop('ip_adresses',$ip_addresses);

		wf($conf['apache']['vhost_conf_dir'].'/000-ispconfig.conf', $tpl->grab());
		unset($tpl);

		//* Gentoo by default does not include .vhost files. Add include line to config file.
		$content = rf($conf['apache']['config_file']);
		if ( strpos($content, 'Include /etc/apache2/vhosts.d/*.vhost') === false ) {
			$content = preg_replace('|(Include /etc/apache2/vhosts.d/\*.conf)|', "$1\nInclude /etc/apache2/vhosts.d/*.vhost", $content);
		}

		$this->write_config_file($conf['apache']['config_file'], $content);

		//* make sure that webalizer finds its config file when it is directly in /etc
		if(is_file('/etc/webalizer.conf') && !is_dir('/etc/webalizer'))
		{
			mkdir('/etc/webalizer', 0755);
			symlink('/etc/webalizer.conf', '/etc/webalizer/webalizer.conf');
		}

		if(is_file('/etc/webalizer/webalizer.conf')) //* Change webalizer mode to incremental
			{
			replaceLine('/etc/webalizer/webalizer.conf', '#IncrementalName', 'IncrementalName webalizer.current', 0, 0);
			replaceLine('/etc/webalizer/webalizer.conf', '#Incremental', 'Incremental     yes', 0, 0);
			replaceLine('/etc/webalizer/webalizer.conf', '#HistoryName', 'HistoryName     webalizer.hist', 0, 0);
		}

		//* add a sshusers group
		if (!is_group('sshusers'))
		{
			$command = 'groupadd sshusers';
			caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
	}

	public function configure_apps_vhost()
	{
		global $conf;

		//* Create the ispconfig apps vhost user and group
		if($conf['apache']['installed'] == true){
			$apps_vhost_user = escapeshellcmd($conf['web']['apps_vhost_user']);
			$apps_vhost_group = escapeshellcmd($conf['web']['apps_vhost_group']);
			$install_dir = escapeshellcmd($conf['web']['website_basedir'].'/apps');

			$command = 'groupadd '.$apps_vhost_user;
			if ( !is_group($apps_vhost_group) ) {
				caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			}

			$command = "useradd -g '$apps_vhost_group' -d $install_dir $apps_vhost_group";
			if ( !is_user($apps_vhost_user) ) {
				caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			}

			//$command = 'adduser '.$conf['apache']['user'].' '.$apps_vhost_group;
      $command = 'usermod -a -G '.$apps_vhost_group.' '.$conf['apache']['user'];
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

			if(!@is_dir($install_dir)){
				mkdir($install_dir, 0755, true);
			} else {
				chmod($install_dir, 0755);
			}
			chown($install_dir, $apps_vhost_user);
			chgrp($install_dir, $apps_vhost_group);

			//* Copy the apps vhost file
			$vhost_conf_dir = $conf['apache']['vhost_conf_dir'];
			$vhost_conf_enabled_dir = $conf['apache']['vhost_conf_enabled_dir'];
			$apps_vhost_servername = ($conf['web']['apps_vhost_servername'] == '') ? '' : 'ServerName '.$conf['web']['apps_vhost_servername'];

			//* Dont just copy over the virtualhost template but add some custom settings
			$content = $this->get_template_file('apache_apps.vhost', true);

			$content = str_replace('{apps_vhost_ip}', $conf['web']['apps_vhost_ip'], $content);
			$content = str_replace('{apps_vhost_port}', $conf['web']['apps_vhost_port'], $content);
			$content = str_replace('{apps_vhost_dir}', $conf['web']['website_basedir'].'/apps', $content);
			$content = str_replace('{website_basedir}', $conf['web']['website_basedir'], $content);
			$content = str_replace('{apps_vhost_servername}', $apps_vhost_servername, $content);

			//* comment out the listen directive if port is 80 or 443
			if($conf['web']['apps_vhost_ip'] == 80 or $conf['web']['apps_vhost_ip'] == 443) {
				$content = str_replace('{vhost_port_listen}', '#', $content);
			} else {
				$content = str_replace('{vhost_port_listen}', '', $content);
			}

			$this->write_config_file("$vhost_conf_dir/apps.vhost", $content);

			//if ( !is_file($conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter') )
			//{
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/apache_apps_fcgi_starter.master', 'tpl/apache_apps_fcgi_starter.master');
			$content = str_replace('{fastcgi_bin}', $conf['fastcgi']['fastcgi_bin'], $content);
			$content = str_replace('{fastcgi_phpini_path}', $conf['fastcgi']['fastcgi_phpini_path'], $content);
			mkdir($conf['web']['website_basedir'].'/php-fcgi-scripts/apps', 0755, true);
			//copy('tpl/apache_apps_fcgi_starter.master',$conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter');
			$this->set_immutable($conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter', false);
			wf($conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter', $content);
			exec('chmod +x '.$conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter');
			exec('chown -R ispapps:ispapps '.$conf['web']['website_basedir'].'/php-fcgi-scripts/apps');
			$this->set_immutable($conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter', true);
			//}
		}
		if($conf['nginx']['installed'] == true){
			$apps_vhost_user = escapeshellcmd($conf['web']['apps_vhost_user']);
			$apps_vhost_group = escapeshellcmd($conf['web']['apps_vhost_group']);
			$install_dir = escapeshellcmd($conf['web']['website_basedir'].'/apps');

			$command = 'groupadd '.$apps_vhost_user;
			if(!is_group($apps_vhost_group)) caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

			$command = 'useradd -g '.$apps_vhost_group.' -d '.$install_dir.' '.$apps_vhost_group;
			if(!is_user($apps_vhost_user)) caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");


			//$command = 'adduser '.$conf['nginx']['user'].' '.$apps_vhost_group;
      $command = 'usermod -a -G '.$apps_vhost_group.' '.$conf['nginx']['user'];
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

			if(!@is_dir($install_dir)){
				mkdir($install_dir, 0755, true);
			} else {
				chmod($install_dir, 0755);
			}
			chown($install_dir, $apps_vhost_user);
			chgrp($install_dir, $apps_vhost_group);

			//* Copy the apps vhost file
			$vhost_conf_dir = $conf['nginx']['vhost_conf_dir'];
			$vhost_conf_enabled_dir = $conf['nginx']['vhost_conf_enabled_dir'];
			$apps_vhost_servername = ($conf['web']['apps_vhost_servername'] == '')?'_':$conf['web']['apps_vhost_servername'];

			// Dont just copy over the virtualhost template but add some custom settings
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/nginx_apps.vhost.master', 'tpl/nginx_apps.vhost.master');

			if($conf['web']['apps_vhost_ip'] == '_default_'){
				$apps_vhost_ip = '';
			} else {
				$apps_vhost_ip = $conf['web']['apps_vhost_ip'].':';
			}

			$socket_dir = escapeshellcmd($conf['nginx']['php_fpm_socket_dir']);
			if(substr($socket_dir, -1) != '/') $socket_dir .= '/';
			if(!is_dir($socket_dir)) exec('mkdir -p '.$socket_dir);
			$fpm_socket = $socket_dir.'apps.sock';
			$cgi_socket = escapeshellcmd($conf['nginx']['cgi_socket']);

			$content = str_replace('{apps_vhost_ip}', $apps_vhost_ip, $content);
			$content = str_replace('{apps_vhost_port}', $conf['web']['apps_vhost_port'], $content);
			$content = str_replace('{apps_vhost_dir}', $conf['web']['website_basedir'].'/apps', $content);
			$content = str_replace('{apps_vhost_servername}', $apps_vhost_servername, $content);
			//$content = str_replace('{fpm_port}', ($conf['nginx']['php_fpm_start_port']+1), $content);
			$content = str_replace('{fpm_socket}', $fpm_socket, $content);
			$content = str_replace('{cgi_socket}', $cgi_socket, $content);

			// SSL in apps vhost is off by default. Might change later.
			$content = str_replace('{ssl_on}', 'ssl', $content);
			$content = str_replace('{ssl_comment}', '#', $content);

			wf($vhost_conf_dir.'/apps.vhost', $content);

			// PHP-FPM
			// Dont just copy over the php-fpm pool template but add some custom settings
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/apps_php_fpm_pool.conf.master', 'tpl/apps_php_fpm_pool.conf.master');
			$content = str_replace('{fpm_pool}', 'apps', $content);
			//$content = str_replace('{fpm_port}', ($conf['nginx']['php_fpm_start_port']+1), $content);
			$content = str_replace('{fpm_socket}', $fpm_socket, $content);
			$content = str_replace('{fpm_user}', $apps_vhost_user, $content);
			$content = str_replace('{fpm_group}', $apps_vhost_group, $content);
			wf($conf['nginx']['php_fpm_pool_dir'].'/apps.conf', $content);

			//copy('tpl/nginx_ispconfig.vhost.master', "$vhost_conf_dir/ispconfig.vhost");
			//* and create the symlink
			if(@is_link($vhost_conf_enabled_dir.'/apps.vhost')) unlink($vhost_conf_enabled_dir.'/apps.vhost');
			if(!@is_link($vhost_conf_enabled_dir.'/000-apps.vhost')) {
				symlink($vhost_conf_dir.'/apps.vhost', $vhost_conf_enabled_dir.'/000-apps.vhost');
			}

		}
	}
  
  public function get_host_ips() {
		$out = array();
		exec("ip addr show | awk '/global/ { print $2 }' | cut -d '/' -f 1", $ret, $val);
		if($val == 0) {
			if(is_array($ret) && !empty($ret)){				
				foreach($ret as $ip) {
					$ip = trim($ip);
          $out[] = $ip;
				}
			}
		}

		return $out;
	}
  
	public function install_ispconfig() {
		global $conf;

		$install_dir = $conf['ispconfig_install_dir'];

		//* Create the ISPConfig installation directory
		if(!@is_dir($install_dir)) {
			$command = "mkdir $install_dir";
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}

		//* Create a ISPConfig user and group
		$command = 'groupadd ispconfig';
		if(!is_group('ispconfig')) caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$command = 'useradd -g ispconfig -d '.$install_dir.' ispconfig';
		if(!is_user('ispconfig')) caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* copy the ISPConfig interface part
		$command = 'cp -rf ../interface '.$install_dir;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* copy the ISPConfig server part
		$command = 'cp -rf ../server '.$install_dir;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Make a backup of the security settings
		if(is_file('/usr/local/ispconfig/security/security_settings.ini')) copy('/usr/local/ispconfig/security/security_settings.ini','/usr/local/ispconfig/security/security_settings.ini~');

		//* copy the ISPConfig security part
		$command = 'cp -rf ../security '.$install_dir;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		$configfile = 'security_settings.ini';
		if(is_file($install_dir.'/security/'.$configfile)) {
			copy($install_dir.'/security/'.$configfile, $install_dir.'/security/'.$configfile.'~');
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		wf($install_dir.'/security/'.$configfile, $content);

		//* Create a symlink, so ISPConfig is accessible via web
		// Replaced by a separate vhost definition for port 8080
		// $command = "ln -s $install_dir/interface/web/ /var/www/ispconfig";
		// caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Create the config file for ISPConfig interface
		$configfile = 'config.inc.php';
		if(is_file($install_dir.'/interface/lib/'.$configfile)) {
			copy($install_dir.'/interface/lib/'.$configfile, $install_dir.'/interface/lib/'.$configfile.'~');
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		$content = str_replace('{mysql_server_ispconfig_user}', $conf['mysql']['ispconfig_user'], $content);
		$content = str_replace('{mysql_server_ispconfig_password}', $conf['mysql']['ispconfig_password'], $content);
		$content = str_replace('{mysql_server_database}', $conf['mysql']['database'], $content);
		$content = str_replace('{mysql_server_host}', $conf['mysql']['host'], $content);
		$content = str_replace('{mysql_server_port}', $conf['mysql']['port'], $content);

		$content = str_replace('{mysql_master_server_ispconfig_user}', $conf['mysql']['master_ispconfig_user'], $content);
		$content = str_replace('{mysql_master_server_ispconfig_password}', $conf['mysql']['master_ispconfig_password'], $content);
		$content = str_replace('{mysql_master_server_database}', $conf['mysql']['master_database'], $content);
		$content = str_replace('{mysql_master_server_host}', $conf['mysql']['master_host'], $content);
		$content = str_replace('{mysql_master_server_port}', $conf['mysql']['master_port'], $content);

		$content = str_replace('{server_id}', $conf['server_id'], $content);
		$content = str_replace('{ispconfig_log_priority}', $conf['ispconfig_log_priority'], $content);
		$content = str_replace('{language}', $conf['language'], $content);
		$content = str_replace('{timezone}', $conf['timezone'], $content);
		$content = str_replace('{theme}', $conf['theme'], $content);
		$content = str_replace('{language_file_import_enabled}', ($conf['language_file_import_enabled'] == true)?'true':'false', $content);

		wf($install_dir.'/interface/lib/'.$configfile, $content);

		//* Create the config file for ISPConfig server
		$configfile = 'config.inc.php';
		if(is_file($install_dir.'/server/lib/'.$configfile)) {
			copy($install_dir.'/server/lib/'.$configfile, $install_dir.'/interface/lib/'.$configfile.'~');
		}
		$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/'.$configfile.'.master', 'tpl/'.$configfile.'.master');
		$content = str_replace('{mysql_server_ispconfig_user}', $conf['mysql']['ispconfig_user'], $content);
		$content = str_replace('{mysql_server_ispconfig_password}', $conf['mysql']['ispconfig_password'], $content);
		$content = str_replace('{mysql_server_database}', $conf['mysql']['database'], $content);
		$content = str_replace('{mysql_server_host}', $conf['mysql']['host'], $content);
		$content = str_replace('{mysql_server_port}', $conf['mysql']['port'], $content);

		$content = str_replace('{mysql_master_server_ispconfig_user}', $conf['mysql']['master_ispconfig_user'], $content);
		$content = str_replace('{mysql_master_server_ispconfig_password}', $conf['mysql']['master_ispconfig_password'], $content);
		$content = str_replace('{mysql_master_server_database}', $conf['mysql']['master_database'], $content);
		$content = str_replace('{mysql_master_server_host}', $conf['mysql']['master_host'], $content);
		$content = str_replace('{mysql_master_server_port}', $conf['mysql']['master_port'], $content);

		$content = str_replace('{server_id}', $conf['server_id'], $content);
		$content = str_replace('{ispconfig_log_priority}', $conf['ispconfig_log_priority'], $content);
		$content = str_replace('{language}', $conf['language'], $content);
		$content = str_replace('{timezone}', $conf['timezone'], $content);
		$content = str_replace('{theme}', $conf['theme'], $content);
		$content = str_replace('{language_file_import_enabled}', ($conf['language_file_import_enabled'] == true)?'true':'false', $content);

		wf($install_dir.'/server/lib/'.$configfile, $content);

		//* Create the config file for remote-actions (but only, if it does not exist, because
		//  the value is a autoinc-value and so changed by the remoteaction_core_module
		if (!file_exists($install_dir.'/server/lib/remote_action.inc.php')) {
			$content = '<?php' . "\n" . '$maxid_remote_action = 0;' . "\n" . '?>';
			wf($install_dir.'/server/lib/remote_action.inc.php', $content);
		}

		//* Enable the server modules and plugins.
		// TODO: Implement a selector which modules and plugins shall be enabled.
		$dir = $install_dir.'/server/mods-available/';
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					if($file != '.' && $file != '..' && substr($file, -8, 8) == '.inc.php') {
						include_once $install_dir.'/server/mods-available/'.$file;
						$module_name = substr($file, 0, -8);
						$tmp = new $module_name;
						if($tmp->onInstall()) {
							if(!@is_link($install_dir.'/server/mods-enabled/'.$file)) {
								@symlink($install_dir.'/server/mods-available/'.$file, $install_dir.'/server/mods-enabled/'.$file);
								// @symlink($install_dir.'/server/mods-available/'.$file, '../mods-enabled/'.$file);
							}
							if (strpos($file, '_core_module') !== false) {
								if(!@is_link($install_dir.'/server/mods-core/'.$file)) {
									@symlink($install_dir.'/server/mods-available/'.$file, $install_dir.'/server/mods-core/'.$file);
									// @symlink($install_dir.'/server/mods-available/'.$file, '../mods-core/'.$file);
								}
							}
						}
						unset($tmp);
					}
				}
				closedir($dh);
			}
		}

		$dir = $install_dir.'/server/plugins-available/';
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					if($conf['apache']['installed'] == true && $file == 'nginx_plugin.inc.php') continue;
					if($conf['nginx']['installed'] == true && $file == 'apache2_plugin.inc.php') continue;
					if($file != '.' && $file != '..' && substr($file, -8, 8) == '.inc.php') {
						include_once $install_dir.'/server/plugins-available/'.$file;
						$plugin_name = substr($file, 0, -8);
						$tmp = new $plugin_name;
						if(method_exists($tmp, 'onInstall') && $tmp->onInstall()) {
							if(!@is_link($install_dir.'/server/plugins-enabled/'.$file)) {
								@symlink($install_dir.'/server/plugins-available/'.$file, $install_dir.'/server/plugins-enabled/'.$file);
								//@symlink($install_dir.'/server/plugins-available/'.$file, '../plugins-enabled/'.$file);
							}
							if (strpos($file, '_core_plugin') !== false) {
								if(!@is_link($install_dir.'/server/plugins-core/'.$file)) {
									@symlink($install_dir.'/server/plugins-available/'.$file, $install_dir.'/server/plugins-core/'.$file);
									//@symlink($install_dir.'/server/plugins-available/'.$file, '../plugins-core/'.$file);
								}
							}
						}
						unset($tmp);
					}
				}
				closedir($dh);
			}
		}

		// Update the server config
		$mail_server_enabled = ($conf['services']['mail'])?1:0;
		$web_server_enabled = ($conf['services']['web'])?1:0;
		$dns_server_enabled = ($conf['services']['dns'])?1:0;
		$file_server_enabled = ($conf['services']['file'])?1:0;
		$db_server_enabled = ($conf['services']['db'])?1:0;
		$vserver_server_enabled = ($conf['openvz']['installed'])?1:0;
		$proxy_server_enabled = ($conf['services']['proxy'])?1:0;
		$firewall_server_enabled = ($conf['services']['firewall'])?1:0;
		$xmpp_server_enabled = ($conf['services']['xmpp'])?1:0;

		$sql = "UPDATE `server` SET mail_server = '$mail_server_enabled', web_server = '$web_server_enabled', dns_server = '$dns_server_enabled', file_server = '$file_server_enabled', db_server = '$db_server_enabled', vserver_server = '$vserver_server_enabled', proxy_server = '$proxy_server_enabled', firewall_server = '$firewall_server_enabled', xmpp_server = '$xmpp_server_enabled' WHERE server_id = ?";

		$this->db->query($sql, $conf['server_id']);
		if($conf['mysql']['master_slave_setup'] == 'y') {
			$this->dbmaster->query($sql, $conf['server_id']);
		}


		// chown install dir to root and chmod 755
		$command = 'chown root:root '.$install_dir;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chmod 755 '.$install_dir;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Chmod the files and directories in the install dir
		$command = 'chmod -R 750 '.$install_dir.'/*';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* chown the interface files to the ispconfig user and group
		$command = 'chown -R ispconfig:ispconfig '.$install_dir.'/interface';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Chmod the files and directories in the acme dir
		$command = 'chmod -R 755 '.$install_dir.'/interface/acme';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* chown the server files to the root user and group
		$command = 'chown -R root:root '.$install_dir.'/server';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* chown the security files to the root user and group
		$command = 'chown -R root:root '.$install_dir.'/security';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* chown the security directory and security_settings.ini to root:ispconfig
		$command = 'chown root:ispconfig '.$install_dir.'/security/security_settings.ini';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chown root:ispconfig '.$install_dir.'/security';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chown root:ispconfig '.$install_dir.'/security/ids.whitelist';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chown root:ispconfig '.$install_dir.'/security/ids.htmlfield';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chown root:ispconfig '.$install_dir.'/security/apache_directives.blacklist';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		$command = 'chown root:ispconfig '.$install_dir.'/security/nginx_directives.blacklist';
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* Make the global language file directory group writable
		exec("chmod -R 770 $install_dir/interface/lib/lang");

		//* Make the temp directory for language file exports writable
		if(is_dir($install_dir.'/interface/web/temp')) exec("chmod -R 770 $install_dir/interface/web/temp");

		//* Make all interface language file directories group writable
		$handle = @opendir($install_dir.'/interface/web');
		while ($file = @readdir($handle)) {
			if ($file != '.' && $file != '..') {
				if(@is_dir($install_dir.'/interface/web'.'/'.$file.'/lib/lang')) {
					$handle2 = opendir($install_dir.'/interface/web'.'/'.$file.'/lib/lang');
					chmod($install_dir.'/interface/web'.'/'.$file.'/lib/lang', 0770);
					while ($lang_file = @readdir($handle2)) {
						if ($lang_file != '.' && $lang_file != '..') {
							chmod($install_dir.'/interface/web'.'/'.$file.'/lib/lang/'.$lang_file, 0770);
						}
					}
				}
			}
		}

		//* Make the APS directories group writable
		exec("chmod -R 770 $install_dir/interface/web/sites/aps_meta_packages");
		exec("chmod -R 770 $install_dir/server/aps_packages");

		//* make sure that the server config file (not the interface one) is only readable by the root user
		chmod($install_dir.'/server/lib/config.inc.php', 0600);
		chown($install_dir.'/server/lib/config.inc.php', 'root');
		chgrp($install_dir.'/server/lib/config.inc.php', 'root');

		//* Make sure thet the interface config file is readable by user ispconfig only
		chmod($install_dir.'/interface/lib/config.inc.php', 0600);
		chown($install_dir.'/interface/lib/config.inc.php', 'ispconfig');
		chgrp($install_dir.'/interface/lib/config.inc.php', 'ispconfig');

		chmod($install_dir.'/server/lib/remote_action.inc.php', 0600);
		chown($install_dir.'/server/lib/remote_action.inc.php', 'root');
		chgrp($install_dir.'/server/lib/remote_action.inc.php', 'root');

		if(@is_file($install_dir.'/server/lib/mysql_clientdb.conf')) {
			chmod($install_dir.'/server/lib/mysql_clientdb.conf', 0600);
			chown($install_dir.'/server/lib/mysql_clientdb.conf', 'root');
			chgrp($install_dir.'/server/lib/mysql_clientdb.conf', 'root');
		}

		if(is_dir($install_dir.'/interface/invoices')) {
			exec('chmod -R 770 '.escapeshellarg($install_dir.'/interface/invoices'));
			exec('chown -R ispconfig:ispconfig '.escapeshellarg($install_dir.'/interface/invoices'));
		}

		exec('chown -R root:root /usr/local/ispconfig/interface/ssl');

		// TODO: FIXME: add the www-data user to the ispconfig group. This is just for testing
		// and must be fixed as this will allow the apache user to read the ispconfig files.
		// Later this must run as own apache server or via suexec!
		if($conf['apache']['installed'] == true){
			$command = 'usermod -a -G ispconfig '.$conf['apache']['user'];
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			if(is_group('ispapps')){
        $command = 'usermod -a -G ispapps '.$conf['apache']['user'];
				caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			}
		}
		if($conf['nginx']['installed'] == true){
      $command = 'usermod -a -G ispconfig '.$conf['nginx']['user'];
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			if(is_group('ispapps')){
        $command = 'usermod -a -G ispapps '.$conf['nginx']['user'];
				caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
			}
		}

		//* Make the shell scripts executable
		$command = "chmod +x $install_dir/server/scripts/*.sh";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		if ($this->install_ispconfig_interface == true && isset($conf['interface_password']) && $conf['interface_password']!='admin') {
			$sql = "UPDATE sys_user SET passwort = ? WHERE username = 'admin';";
			$this->db->query($sql, $this->crypt_password($conf['interface_password']));
		}

		if($conf['apache']['installed'] == true && $this->install_ispconfig_interface == true){
			//* Copy the ISPConfig vhost for the controlpanel
			$vhost_conf_dir = $conf['apache']['vhost_conf_dir'];
			//$vhost_conf_enabled_dir = $conf['apache']['vhost_conf_enabled_dir'];

			// Dont just copy over the virtualhost template but add some custom settings
			$tpl = new tpl();
  		if (file_exists($conf['ispconfig_install_dir']."/server/conf-custom/install/apache_ispconfig.vhost.master")) {
  			$tpl->newTemplate($conf['ispconfig_install_dir']."/server/conf-custom/install/apache_ispconfig.vhost.master");
  		} else {
  			$tpl->newTemplate("dist/tpl/gentoo/apache_ispconfig.vhost.master");
  		}
			$tpl->setVar('vhost_port',$conf['apache']['vhost_port']);

			// comment out the listen directive if port is 80 or 443
			if($conf['apache']['vhost_port'] == 80 or $conf['apache']['vhost_port'] == 443) {
				$tpl->setVar('vhost_port_listen','#');
			} else {
				$tpl->setVar('vhost_port_listen','');
			}

			if(is_file($install_dir.'/interface/ssl/ispserver.crt') && is_file($install_dir.'/interface/ssl/ispserver.key')) {
				$tpl->setVar('ssl_comment','');
			} else {
				$tpl->setVar('ssl_comment','#');
			}
			if(is_file($install_dir.'/interface/ssl/ispserver.crt') && is_file($install_dir.'/interface/ssl/ispserver.key') && is_file($install_dir.'/interface/ssl/ispserver.bundle')) {
				$tpl->setVar('ssl_bundle_comment','');
			} else {
				$tpl->setVar('ssl_bundle_comment','#');
			}

			$tpl->setVar('apache_version',getapacheversion());

			wf($vhost_conf_dir.'/ispconfig.vhost', $tpl->grab());

			//* and create the symlink
			/*if($this->is_update == false) {
				if(@is_link($vhost_conf_enabled_dir.'/ispconfig.vhost')) unlink($vhost_conf_enabled_dir.'/ispconfig.vhost');
				if(!@is_link($vhost_conf_enabled_dir.'/000-ispconfig.vhost')) {
					symlink($vhost_conf_dir.'/ispconfig.vhost', $vhost_conf_enabled_dir.'/000-ispconfig.vhost');
				}
			}*/
			//if(!is_file('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter')) {
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/apache_ispconfig_fcgi_starter.master', 'tpl/apache_ispconfig_fcgi_starter.master');
			$content = str_replace('{fastcgi_bin}', $conf['fastcgi']['fastcgi_bin'], $content);
			$content = str_replace('{fastcgi_phpini_path}', $conf['fastcgi']['fastcgi_phpini_path'], $content);
			@mkdir('/var/www/php-fcgi-scripts/ispconfig', 0755, true);
			$this->set_immutable('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter', false);
			wf('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter', $content);
			exec('chmod +x /var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter');
			@symlink($install_dir.'/interface/web', '/var/www/ispconfig');
			exec('chown -R ispconfig:ispconfig /var/www/php-fcgi-scripts/ispconfig');
			$this->set_immutable('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter', true);
			//}
      
      // unlink acme vhost symlink
      if(is_link($vhost_conf_dir . '/999-acme.conf') && file_exists($vhost_conf_dir . '/acme.conf')) unlink($vhost_conf_dir . '/999-acme.conf');
		}

		if($conf['nginx']['installed'] == true && $this->install_ispconfig_interface == true){
			//* Copy the ISPConfig vhost for the controlpanel
			$vhost_conf_dir = $conf['nginx']['vhost_conf_dir'];
			$vhost_conf_enabled_dir = $conf['nginx']['vhost_conf_enabled_dir'];

			// Dont just copy over the virtualhost template but add some custom settings
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/nginx_ispconfig.vhost.master', 'tpl/nginx_ispconfig.vhost.master');
			$content = str_replace('{vhost_port}', $conf['nginx']['vhost_port'], $content);

			if(is_file($install_dir.'/interface/ssl/ispserver.crt') && is_file($install_dir.'/interface/ssl/ispserver.key')) {
				$content = str_replace('{ssl_on}', 'ssl http2', $content);
				$content = str_replace('{ssl_comment}', '', $content);
				$content = str_replace('{fastcgi_ssl}', 'on', $content);
			} else {
				$content = str_replace('{ssl_on}', '', $content);
				$content = str_replace('{ssl_comment}', '#', $content);
				$content = str_replace('{fastcgi_ssl}', 'off', $content);
			}

			$socket_dir = escapeshellcmd($conf['nginx']['php_fpm_socket_dir']);
			if(substr($socket_dir, -1) != '/') $socket_dir .= '/';
			if(!is_dir($socket_dir)) exec('mkdir -p '.$socket_dir);
			$fpm_socket = $socket_dir.'ispconfig.sock';

			//$content = str_replace('{fpm_port}', $conf['nginx']['php_fpm_start_port'], $content);
			$content = str_replace('{fpm_socket}', $fpm_socket, $content);

			wf($vhost_conf_dir.'/ispconfig.vhost', $content);

			unset($content);

			// PHP-FPM
			// Dont just copy over the php-fpm pool template but add some custom settings
			$content = rfsel($conf['ispconfig_install_dir'].'/server/conf-custom/install/php_fpm_pool.conf.master', 'tpl/php_fpm_pool.conf.master');
			$content = str_replace('{fpm_pool}', 'ispconfig', $content);
			//$content = str_replace('{fpm_port}', $conf['nginx']['php_fpm_start_port'], $content);
			$content = str_replace('{fpm_socket}', $fpm_socket, $content);
			$content = str_replace('{fpm_user}', 'ispconfig', $content);
			$content = str_replace('{fpm_group}', 'ispconfig', $content);
			wf($conf['nginx']['php_fpm_pool_dir'].'/ispconfig.conf', $content);

			//copy('tpl/nginx_ispconfig.vhost.master', $vhost_conf_dir.'/ispconfig.vhost');
			//* and create the symlink
			if($this->is_update == false) {
				if(@is_link($vhost_conf_enabled_dir.'/ispconfig.vhost')) unlink($vhost_conf_enabled_dir.'/ispconfig.vhost');
				if(!@is_link($vhost_conf_enabled_dir.'/000-ispconfig.vhost')) {
					symlink($vhost_conf_dir.'/ispconfig.vhost', $vhost_conf_enabled_dir.'/000-ispconfig.vhost');
				}
			}
		}

		//* Install the update script
		if(is_file('/usr/local/bin/ispconfig_update_from_dev.sh')) unlink('/usr/local/bin/ispconfig_update_from_dev.sh');
		chown($install_dir.'/server/scripts/update_from_dev.sh', 'root');
		chmod($install_dir.'/server/scripts/update_from_dev.sh', 0700);
//		chown($install_dir.'/server/scripts/update_from_tgz.sh', 'root');
//		chmod($install_dir.'/server/scripts/update_from_tgz.sh', 0700);
		chown($install_dir.'/server/scripts/ispconfig_update.sh', 'root');
		chmod($install_dir.'/server/scripts/ispconfig_update.sh', 0700);
		if(!is_link('/usr/local/bin/ispconfig_update_from_dev.sh')) symlink($install_dir.'/server/scripts/ispconfig_update.sh', '/usr/local/bin/ispconfig_update_from_dev.sh');
		if(!is_link('/usr/local/bin/ispconfig_update.sh')) symlink($install_dir.'/server/scripts/ispconfig_update.sh', '/usr/local/bin/ispconfig_update.sh');

		// Install ISPConfig cli command
		if(is_file('/usr/local/bin/ispc')) unlink('/usr/local/bin/ispc');
		chown($install_dir.'/server/cli/ispc', 'root');
		chmod($install_dir.'/server/cli/ispc', 0700);
		symlink($install_dir.'/server/cli/ispc', '/usr/local/bin/ispc');

		// Make executable then unlink and symlink letsencrypt pre, post and renew hook scripts
		chown($install_dir.'/server/scripts/letsencrypt_pre_hook.sh', 'root');
		chown($install_dir.'/server/scripts/letsencrypt_post_hook.sh', 'root');
		chown($install_dir.'/server/scripts/letsencrypt_renew_hook.sh', 'root');
		chmod($install_dir.'/server/scripts/letsencrypt_pre_hook.sh', 0700);
		chmod($install_dir.'/server/scripts/letsencrypt_post_hook.sh', 0700);
		chmod($install_dir.'/server/scripts/letsencrypt_renew_hook.sh', 0700);
		if(is_link('/usr/local/bin/letsencrypt_pre_hook.sh')) unlink('/usr/local/bin/letsencrypt_pre_hook.sh');
		if(is_link('/usr/local/bin/letsencrypt_post_hook.sh')) unlink('/usr/local/bin/letsencrypt_post_hook.sh');
		if(is_link('/usr/local/bin/letsencrypt_renew_hook.sh')) unlink('/usr/local/bin/letsencrypt_renew_hook.sh');
		symlink($install_dir.'/server/scripts/letsencrypt_pre_hook.sh', '/usr/local/bin/letsencrypt_pre_hook.sh');
		symlink($install_dir.'/server/scripts/letsencrypt_post_hook.sh', '/usr/local/bin/letsencrypt_post_hook.sh');
		symlink($install_dir.'/server/scripts/letsencrypt_renew_hook.sh', '/usr/local/bin/letsencrypt_renew_hook.sh');

		//* Make the logs readable for the ispconfig user
		if(@is_file('/var/log/mail.log')) exec('chmod +r /var/log/mail.log');
		if(@is_file('/var/log/mail.warn')) exec('chmod +r /var/log/mail.warn');
		if(@is_file('/var/log/mail.err')) exec('chmod +r /var/log/mail.err');
		if(@is_file('/var/log/messages')) exec('chmod +r /var/log/messages');
		if(@is_file('/var/log/clamav/clamav.log')) exec('chmod +r /var/log/clamav/clamav.log');
		if(@is_file('/var/log/clamav/freshclam.log')) exec('chmod +r /var/log/clamav/freshclam.log');

		//* Create the ispconfig log file and directory
		if(!is_file($conf['ispconfig_log_dir'].'/ispconfig.log')) {
			if(!is_dir($conf['ispconfig_log_dir'])) mkdir($conf['ispconfig_log_dir'], 0755);
			touch($conf['ispconfig_log_dir'].'/ispconfig.log');
		}
		chmod($conf['ispconfig_log_dir'].'/ispconfig.log', 0600);

		//* Create the ispconfig auth log file and set uid/gid
		if(!is_file($conf['ispconfig_log_dir'].'/auth.log')) {
			touch($conf['ispconfig_log_dir'].'/auth.log');
		}
		exec('chown ispconfig:ispconfig '. $conf['ispconfig_log_dir'].'/auth.log');
		exec('chmod 660 '. $conf['ispconfig_log_dir'].'/auth.log');

		if(is_user('getmail')) {
			rename($install_dir.'/server/scripts/run-getmail.sh', '/usr/local/bin/run-getmail.sh');
			if(is_user('getmail')) chown('/usr/local/bin/run-getmail.sh', 'getmail');
			chmod('/usr/local/bin/run-getmail.sh', 0744);
		}

		//* Add Log-Rotation
		if (is_dir('/etc/logrotate.d')) {
			@unlink('/etc/logrotate.d/logispc3'); // ignore, if the file is not there
			/* We rotate these logs in cron_daily.php
			$fh = fopen('/etc/logrotate.d/logispc3', 'w');
			fwrite($fh,
					"$conf['ispconfig_log_dir']/ispconfig.log { \n" .
					"	weekly \n" .
					"	missingok \n" .
					"	rotate 4 \n" .
					"	compress \n" .
					"	delaycompress \n" .
					"} \n" .
					"$conf['ispconfig_log_dir']/cron.log { \n" .
					"	weekly \n" .
					"	missingok \n" .
					"	rotate 4 \n" .
					"	compress \n" .
					"	delaycompress \n" .
					"}");
			fclose($fh);
			*/
		}

		//* Remove Domain module as its functions are available in the client module now
		if(@is_dir('/usr/local/ispconfig/interface/web/domain')) exec('rm -rf /usr/local/ispconfig/interface/web/domain');

		//* Disable rkhunter run and update in debian cronjob as ispconfig is running and updating rkhunter
		if(is_file('/etc/default/rkhunter')) {
			replaceLine('/etc/default/rkhunter', 'CRON_DAILY_RUN="yes"', 'CRON_DAILY_RUN="no"', 1, 0);
			replaceLine('/etc/default/rkhunter', 'CRON_DB_UPDATE="yes"', 'CRON_DB_UPDATE="no"', 1, 0);
		}

		// Add symlink for patch tool
		if(!is_link('/usr/local/bin/ispconfig_patch')) exec('ln -s /usr/local/ispconfig/server/scripts/ispconfig_patch /usr/local/bin/ispconfig_patch');

		// Change mode of a few files from amavisd
		if(is_file($conf['amavis']['config_dir'].'/conf.d/50-user')) chmod($conf['amavis']['config_dir'].'/conf.d/50-user', 0640);
		if(is_file($conf['amavis']['config_dir'].'/50-user~')) chmod($conf['amavis']['config_dir'].'/50-user~', 0400);
		if(is_file($conf['amavis']['config_dir'].'/amavisd.conf')) chmod($conf['amavis']['config_dir'].'/amavisd.conf', 0640);
		if(is_file($conf['amavis']['config_dir'].'/amavisd.conf~')) chmod($conf['amavis']['config_dir'].'/amavisd.conf~', 0400);
	}

}

?>

