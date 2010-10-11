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
	
	public function configure_postfix($options = '')
    {
        global $conf;
		
        $cf = $conf['postfix'];
		$config_dir = $cf['config_dir'];
        
		if(!is_dir($config_dir)){
            $this->error("The postfix configuration directory '$config_dir' does not exist.");
        }
        
        //* Install virtual mappings
        foreach (glob('tpl/mysql-virtual_*.master') as $filename) {
        	$this->process_postfix_config( basename($filename, '.master') );
        }
        
		//* Changing mode and group of the new created config files.
		caselog('chmod o= '.$config_dir.'/mysql-virtual_*.cf* &> /dev/null',
                 __FILE__, __LINE__, 'chmod on mysql-virtual_*.cf*', 'chmod on mysql-virtual_*.cf* failed');
		caselog('chgrp '.$cf['group'].' '.$config_dir.'/mysql-virtual_*.cf* &> /dev/null', 
                __FILE__, __LINE__, 'chgrp on mysql-virtual_*.cf*', 'chgrp on mysql-virtual_*.cf* failed');
		
		//* Creating virtual mail user and group
		$command = 'groupadd -g '.$cf['vmail_groupid'].' '.$cf['vmail_groupname'];
		if (!is_group($cf['vmail_groupname'])) {
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}

		$command = 'useradd -g '.$cf['vmail_groupname'].' -u '.$cf['vmail_userid'].' '.$cf['vmail_username'].' -d '.$cf['vmail_mailbox_base'].' -m';
		if (!is_user($cf['vmail_username'])) {
			caselog("$command &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");		
		}

		$postconf_commands = array (
			'myhostname = '.$conf['hostname'],
			'mydestination = '.$conf['hostname'].', localhost, localhost.localdomain',
			'mynetworks = 127.0.0.0/8 [::1]/128',
			'virtual_alias_domains =',
			'virtual_alias_maps = proxy:mysql:'.$config_dir.'/mysql-virtual_forwardings.cf, mysql:'.$config_dir.'/mysql-virtual_email2email.cf',
			'virtual_mailbox_domains = proxy:mysql:'.$config_dir.'/mysql-virtual_domains.cf',
			'virtual_mailbox_maps = proxy:mysql:'.$config_dir.'/mysql-virtual_mailboxes.cf',
			'virtual_mailbox_base = '.$cf['vmail_mailbox_base'],
			'virtual_uid_maps = static:'.$cf['vmail_userid'],
			'virtual_gid_maps = static:'.$cf['vmail_groupid'],
			'smtpd_sasl_auth_enable = yes',
			'broken_sasl_auth_clients = yes',
			'smtpd_sasl_authenticated_header = yes',
			'smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, check_recipient_access mysql:'.$config_dir.'/mysql-virtual_recipient.cf, reject_unauth_destination',
			'smtpd_use_tls = yes',
			'smtpd_tls_security_level = may',
			'smtpd_tls_cert_file = '.$config_dir.'/smtpd.cert',
			'smtpd_tls_key_file = '.$config_dir.'/smtpd.key',
			'transport_maps = proxy:mysql:'.$config_dir.'/mysql-virtual_transports.cf',
			'relay_domains = mysql:'.$config_dir.'/mysql-virtual_relaydomains.cf',
		    'relay_recipient_maps = mysql:'.$config_dir.'/mysql-virtual_relayrecipientmaps.cf',
			'virtual_create_maildirsize = yes',
			'virtual_maildir_extended = yes',
			'virtual_mailbox_limit_maps = proxy:mysql:'.$config_dir.'/mysql-virtual_mailbox_limit_maps.cf',
			'virtual_mailbox_limit_override = yes',
			'virtual_maildir_limit_message = "The user you are trying to reach is over quota."',
			'virtual_overquota_bounce = yes',
			'proxy_read_maps = $local_recipient_maps $mydestination $virtual_alias_maps $virtual_alias_domains $virtual_mailbox_maps $virtual_mailbox_domains $relay_recipient_maps $relay_domains $canonical_maps $sender_canonical_maps $recipient_canonical_maps $relocated_maps $transport_maps $mynetworks $virtual_mailbox_limit_maps',
			'smtpd_sender_restrictions = check_sender_access mysql:'.$config_dir.'/mysql-virtual_sender.cf',
			'smtpd_client_restrictions = check_client_access mysql:'.$config_dir.'/mysql-virtual_client.cf',
			'maildrop_destination_concurrency_limit = 1',
			'maildrop_destination_recipient_limit   = 1',
			'virtual_transport = maildrop',
			'header_checks = regexp:'.$config_dir.'/header_checks',
			'mime_header_checks = regexp:'.$config_dir.'/mime_header_checks',
			'nested_header_checks = regexp:'.$config_dir.'/nested_header_checks',
			'body_checks = regexp:'.$config_dir.'/body_checks'
		);
		
		//* Create the header and body check files
		touch($config_dir.'/header_checks');
		touch($config_dir.'/mime_header_checks');
		touch($config_dir.'/nested_header_checks');
		touch($config_dir.'/body_checks');
		
		
		//* Make a backup copy of the main.cf file
		copy($config_dir.'/main.cf', $config_dir.'/main.cf~');
		
		//* Executing the postconf commands
		foreach($postconf_commands as $cmd) {
			$command = "postconf -e '$cmd'";
			caselog($command.' &> /dev/null', __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);
		}
		
		//* Create the SSL certificate
		if (!stristr($options,'dont-create-certs'))  
		{
			$command = 'cd '.$config_dir.'; '
                      .'openssl req -new -outform PEM -out smtpd.cert -newkey rsa:2048 -nodes -keyout smtpd.key -keyform PEM -days 365 -x509';
			exec($command);
		
			$command = 'chmod o= '.$config_dir.'/smtpd.key';
			caselog($command.' &> /dev/null', __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);
		}
		
		//* We have to change the permissions of the courier authdaemon directory to make it accessible for maildrop.
		$command = 'chmod 755  /var/lib/courier/authdaemon/';
		if (is_dir('/var/lib/courier/authdaemon')) {
			caselog($command.' &> /dev/null', __FILE__, __LINE__, 'EXECUTED: '.$command, 'Failed to execute the command '.$command);
		}
		
		//* Changing maildrop lines in posfix master.cf
		$configfile = $config_dir.'/master.cf';
		$content = rf($configfile);

        $content = preg_replace('/^#?maildrop/m', 'maildrop', $content);
        $content = preg_replace('/^#?(\s+)flags=DRhu user=vmail argv=\/usr\/bin\/maildrop -d/m',
        						'$1flags=DRhu user=vmail argv=/usr/bin/maildrop -d vmail \${extension} \${recipient} \${user} \${nexthop} \${sender}',
        						$content);
        						
		$this->write_config_file($configfile, $content);
		
		//* Writing the Maildrop mailfilter file
		$content = rf('tpl/mailfilter.master');
		$content = str_replace('{dist_postfix_vmail_mailbox_base}', $cf['vmail_mailbox_base'], $content);
		
		$this->write_config_file($cf['vmail_mailbox_base'].'/.mailfilter', $content);
		
		//* Create the directory for the custom mailfilters
		if (!is_dir($cf['vmail_mailbox_base'].'/mailfilters')) 
		{
			$command = 'mkdir '.$cf['vmail_mailbox_base'].'/mailfilters';
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
		
		//* Chmod and chown the .mailfilter file
		$command = 'chown -R '.$cf['vmail_username'].':'.$cf['vmail_groupname'].' '.$cf['vmail_mailbox_base'].'/.mailfilter';
		caselog($command." &> /dev/null", __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		$command = 'chmod -R 600 '.$cf['vmail_mailbox_base'].'/.mailfilter';
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
    
    public function configure_dovecot() 
    {
    	global $conf;
    	
    	$config_dir = $conf['dovecot']['config_dir'];
    	
    	$configfile = $conf['postfix']['config_dir'].'/master.cf';
    	
    	if(is_file($configfile)) {
			copy($configfile, $configfile.'~2');
		}
		if(is_file($configfile.'~2')) {
			chmod($configfile.'~2', 0400);
		}
    	
    	//* Configure master.cf and add a line for deliver
		$content = rf($configfile);
		
    	if(!stristr($content,'dovecot/deliver')) {
			$deliver_content = 'dovecot   unix  -       n       n       -       -       pipe'."\n".'  flags=DRhu user=vmail:vmail argv=/usr/libexec/dovecot/deliver -f ${sender} -d ${user}@${nexthop}';
			af($conf['postfix']['config_dir'].'/master.cf',$deliver_content);
		}
		unset($content);
		unset($deliver_content);
		unset($configfile);
		
		//* Reconfigure postfix to use dovecot authentication
		$postconf_commands = array (
				'dovecot_destination_recipient_limit = 1',
				'virtual_transport = dovecot',
				'smtpd_sasl_type = dovecot',
				'smtpd_sasl_path = private/auth'
		);
		
		//* Make a backup copy of the main.cf file
		copy($conf['postfix']['config_dir'].'/main.cf',$conf['postfix']['config_dir'].'/main.cf~3');
		
    	//* Executing the postconf commands
		foreach($postconf_commands as $cmd) 
		{
			$command = "postconf -e '$cmd'";
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
		
		//* copy dovecot.conf
		$configfile = $config_dir.'/dovecot.conf';
		$content = $this->get_template_file('dovecot.conf', true);
		$this->write_config_file($configfile, $content);
		
		//* dovecot-sql.conf
		$configfile = $config_dir.'/dovecot-sql.conf';
		$content = $this->get_template_file('debian_dovecot-sql.conf', true, true);
		$this->write_config_file($configfile, $content);
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
		
		//* Append the configuration for amavisd to the master.cf file
		$content = rf($conf['postfix']['config_dir'].'/master.cf');
		
		if(!stristr($content,'127.0.0.1:10025')) //* Only add the content if we had not addded it before 
		{ 
			unset($content);
			$content = $this->get_template_file('master_cf_amavis', true);
			af($conf['postfix']['config_dir'].'/master.cf', $content);
		}
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
		$content = preg_replace('/MISC_OTHER="[^"]+"/', 'MISC_OTHER="-b -A -E -Z -D -H -O clf:'.$logdir.'/transfer.log"', $content);
		
		$this->write_config_file($conf['pureftpd']['config_file'], $content);
    }
    
	public function configure_powerdns() 
	{
		global $conf;
		
		//* Create the database
		if(!$this->db->query('CREATE DATABASE IF NOT EXISTS '.$conf['powerdns']['database'].' DEFAULT CHARACTER SET '.$conf['mysql']['charset'])) {
			$this->error('Unable to create MySQL database: '.$conf['powerdns']['database'].'.');
		}
		
		//* Create the ISPConfig database user in the local database
        $query = 'GRANT ALL ON `'.$conf['powerdns']['database'].'` . * TO \''.$conf['mysql']['ispconfig_user'].'\'@\'localhost\';';
		if(!$this->db->query($query)) {
			$this->error('Unable to create user for powerdns database Error: '.$this->db->errorMessage);
		}
		
		//* Reload database privelages
		$this->db->query('FLUSH PRIVILEGES;');
		
		//* load the powerdns databse dump
		if($conf['mysql']['admin_password'] == '') {
			caselog("mysql --default-character-set=".$conf['mysql']['charset']." -h '".$conf['mysql']['host']."' -u '".$conf['mysql']['admin_user']."' '".$conf['powerdns']['database']."' < '".ISPC_INSTALL_ROOT."/install/sql/powerdns.sql' &> /dev/null", 
                    __FILE__, __LINE__, 'read in ispconfig3.sql', 'could not read in powerdns.sql');
		} else {
			caselog("mysql --default-character-set=".$conf['mysql']['charset']." -h '".$conf['mysql']['host']."' -u '".$conf['mysql']['admin_user']."' -p'".$conf['mysql']['admin_password']."' '".$conf['powerdns']['database']."' < '".ISPC_INSTALL_ROOT."/install/sql/powerdns.sql' &> /dev/null", 
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
	
	public function configure_apache()
    {	
		global $conf;
		
		//* Create the logging directory for the vhost logfiles
		if (!is_dir($conf['ispconfig_log_dir'].'/httpd')) {
			mkdir($conf['ispconfig_log_dir'].'/httpd', 0755, true);
		}
		
		if (is_file($conf['suphp']['config_file'])) 
		{
			$content = rf($conf['suphp']['config_file']);
			
			if (!preg_match('|^x-httpd-suphp=php:/usr/bin/php-cgi$|m', $content))
			{
				$content = preg_replace('/;Handler for php-scripts/',";Handler for php-scripts\nx-httpd-suphp=php:/usr/bin/php-cgi", $content);
				$content = preg_replace('/;?umask=\d+/','umask=0022', $content);
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
		$content = $this->get_template_file('apache_ispconfig.conf', true);
		
		$records = $this->db->queryAllRecords("SELECT * FROM server_ip WHERE server_id = ".$conf["server_id"]." AND virtualhost = 'y'");
		if(is_array($records) && count($records) > 0) 
		{
			foreach($records as $rec) {
				$content .= "NameVirtualHost ".$rec["ip_address"].":80\n";
				$content .= "NameVirtualHost ".$rec["ip_address"].":443\n";
			}
		}
		
		$this->write_config_file($conf['apache']['vhost_conf_dir'].'/000-ispconfig.conf', $content);
		
		//* Gentoo by default does not include .vhost files. Add include line to config file.
		$content = rf($conf['apache']['config_file']);
		if ( strpos($content, 'Include /etc/apache2/vhosts.d/*.vhost') === false ) {
			$content = preg_replace('|(Include /etc/apache2/vhosts.d/\*.conf)|',"$1\nInclude /etc/apache2/vhosts.d/*.vhost", $content);
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
    		replaceLine('/etc/webalizer/webalizer.conf','#IncrementalName','IncrementalName webalizer.current',0,0);
			replaceLine('/etc/webalizer/webalizer.conf','#Incremental','Incremental     yes',0,0);
			replaceLine('/etc/webalizer/webalizer.conf','#HistoryName','HistoryName     webalizer.hist',0,0);
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
		
		$command = 'adduser '.$conf['apache']['user'].' '.$apps_vhost_group;
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		if (!@is_dir($install_dir)) {
			mkdir($install_dir, 0755, true);
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
		
		if ( !is_file($conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter') ) 
		{
			mkdir($conf['web']['website_basedir'].'/php-fcgi-scripts/apps', 0755, true);
			copy('tpl/apache_apps_fcgi_starter.master',$conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter');
			exec('chmod +x '.$conf['web']['website_basedir'].'/php-fcgi-scripts/apps/.php-fcgi-starter');
			exec('chown -R ispapps:ispapps '.$conf['web']['website_basedir'].'/php-fcgi-scripts/apps');
			
		}
	}
    
    public function install_ispconfig()
    {
		global $conf;
		
		$install_dir = $conf['ispconfig_install_dir'];
		
    	//* Create the ISPConfig installation directory
		if(!is_dir($install_dir)) 
		{
			$command = "mkdir $install_dir";
			caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
		
		//* Create a ISPConfig user and group
		if (!is_group('ispconfig')) 
		{
			$command = 'groupadd ispconfig';
			caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
		
		if (!is_user('ispconfig')) 
		{
			$command = "useradd -g ispconfig -d $install_dir ispconfig";
			caselog($command.' &> /dev/null 2> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		}
		
		//* copy the ISPConfig interface part
		$command = "cp -rf ../interface $install_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		//* copy the ISPConfig server part
		$command = "cp -rf ../server $install_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		
		//* Create the config file for ISPConfig interface
		$configfile = 'config.inc.php';
		$content = $this->get_template_file($configfile, true, true); //* get contents & insert db cred
		
		$content = str_replace('{mysql_master_server_ispconfig_user}', $conf['mysql']['master_ispconfig_user'], $content);
		$content = str_replace('{mysql_master_server_ispconfig_password}', $conf['mysql']['master_ispconfig_password'], $content);
		$content = str_replace('{mysql_master_server_database}', $conf['mysql']['master_database'], $content);
		$content = str_replace('{mysql_master_server_host}', $conf['mysql']['master_host'], $content);
		
		$content = str_replace('{server_id}', $conf['server_id'], $content);
		$content = str_replace('{ispconfig_log_priority}', $conf['ispconfig_log_priority'], $content);
		$content = str_replace('{language}', $conf['language'], $content);
		
		$this->write_config_file("$install_dir/interface/lib/$configfile", $content);
		
		//* Create the config file for ISPConfig server
		$this->write_config_file("$install_dir/server/lib/$configfile", $content);
		
    	//* Create the config file for remote-actions (but only, if it does not exist, because
		//* the value is a autoinc-value and so changed by the remoteaction_core_module
		if (!file_exists($install_dir.'/server/lib/remote_action.inc.php')) {
			$content = '<?php' . "\n" . '$maxid_remote_action = 0;' . "\n" . '?>';
			$this->write_config_file("$install_dir/server/lib/remote_action.inc.php", $content);
		}
		
    	//* Enable the server modules and plugins.
		// TODO: Implement a selector which modules and plugins shall be enabled.
		$dir = $install_dir.'/server/mods-available/';
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					if($file != '.' && $file != '..' && substr($file,-8,8) == '.inc.php') {
						include_once($install_dir.'/server/mods-available/'.$file);
						$module_name = substr($file,0,-8);
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
					if($file != '.' && $file != '..' && substr($file,-8,8) == '.inc.php') {
						include_once($install_dir.'/server/plugins-available/'.$file);
						$plugin_name = substr($file,0,-8);
						$tmp = new $plugin_name;
						if(method_exists($tmp,'onInstall') && $tmp->onInstall()) {
							if(!@is_link($install_dir.'/server/plugins-enabled/'.$file)) {
								@symlink($install_dir.'/server/plugins-available/'.$file, $install_dir.'/server/plugins-enabled/'.$file);
							}
							if (strpos($file, '_core_plugin') !== false) {
								if(!@is_link($install_dir.'/server/plugins-core/'.$file)) {
									@symlink($install_dir.'/server/plugins-available/'.$file, $install_dir.'/server/plugins-core/'.$file);
								}
							}
						}
						unset($tmp);
					}
				}
				closedir($dh);
			}
		}
		
		//* Update the server config
		$mail_server_enabled = ($conf['services']['mail'])?1:0;
		$web_server_enabled = ($conf['services']['web'])?1:0;
		$dns_server_enabled = ($conf['services']['dns'])?1:0;
		$file_server_enabled = ($conf['services']['file'])?1:0;
		$db_server_enabled = ($conf['services']['db'])?1:0;
		$vserver_server_enabled = ($conf['services']['vserver'])?1:0;
		
    	$sql = "UPDATE `server` SET mail_server = '$mail_server_enabled', web_server = '$web_server_enabled', dns_server = '$dns_server_enabled', file_server = '$file_server_enabled', db_server = '$db_server_enabled', vserver_server = '$vserver_server_enabled' WHERE server_id = ".intval($conf['server_id']);
		
		if($conf['mysql']['master_slave_setup'] == 'y') {
			$this->dbmaster->query($sql);
			$this->db->query($sql);
		} else {
			$this->db->query($sql);
		}
		
		//* Chmod the files
		$command = "chmod -R 750 $install_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");

		//* chown the files to the ispconfig user and group
		$command = "chown -R ispconfig:ispconfig $install_dir";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		//* Make the global language file directory group writable
		exec("chmod -R 770 $install_dir/interface/lib/lang");
		
		//* Make the temp directory for language file exports writable
		if(is_dir($install_dir.'/interface/web/temp')) {
			exec("chmod -R 770 $install_dir/interface/web/temp");
		}
		
	    //* Make all interface language file directories group writable
		$handle = @opendir($install_dir.'/interface/web');
		while ($file = @readdir ($handle)) { 
	   		if ($file != '.' && $file != '..') {
	        	if(@is_dir($install_dir.'/interface/web'.'/'.$file.'/lib/lang')) {
					$handle2 = opendir($install_dir.'/interface/web'.'/'.$file.'/lib/lang');
					chmod($install_dir.'/interface/web'.'/'.$file.'/lib/lang',0770);
					while ($lang_file = @readdir ($handle2)) {
						if ($lang_file != '.' && $lang_file != '..') {
							chmod($install_dir.'/interface/web'.'/'.$file.'/lib/lang/'.$lang_file,0770);
						}
					}
				}
			}
		}
		
	    //* make sure that the server config file (not the interface one) is only readable by the root user
    	chmod($install_dir.'/server/lib/'.$configfile, 0600);
		chown($install_dir.'/server/lib/'.$configfile, 'root');
		chgrp($install_dir.'/server/lib/'.$configfile, 'root');

		chmod($install_dir.'/server/lib/remote_action.inc.php', 0600);
		chown($install_dir.'/server/lib/remote_action.inc.php', 'root');
		chgrp($install_dir.'/server/lib/remote_action.inc.php', 'root');

		if(@is_file($install_dir.'/server/lib/mysql_clientdb.conf')) {
			chmod($install_dir.'/server/lib/mysql_clientdb.conf', 0600);
			chown($install_dir.'/server/lib/mysql_clientdb.conf', 'root');
			chgrp($install_dir.'/server/lib/mysql_clientdb.conf', 'root');
		}
		
		// TODO: FIXME: add the www-data user to the ispconfig group. This is just for testing
		// and must be fixed as this will allow the apache user to read the ispconfig files.
		// Later this must run as own apache server or via suexec!
		$command = 'usermod -a -G ispconfig '.$conf['apache']['user'];
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		//* Make the shell scripts executable
		$command = "chmod +x $install_dir/server/scripts/*.sh";
		caselog($command.' &> /dev/null', __FILE__, __LINE__, "EXECUTED: $command", "Failed to execute the command $command");
		
		//* Copy the ISPConfig vhost for the controlpanel
		$content = $this->get_template_file("apache_ispconfig.vhost", true);
		$content = str_replace('{vhost_port}', $conf['apache']['vhost_port'], $content);
		
		//* comment out the listen directive if port is 80 or 443
		if ($conf['apache']['vhost_port'] == 80 or $conf['apache']['vhost_port'] == 443) {
			$content = str_replace('{vhost_port_listen}', '#', $content);
		} else {
			$content = str_replace('{vhost_port_listen}', '', $content);
		}
		
		if(is_file($install_dir.'/interface/ssl/ispserver.crt') && is_file($install_dir.'/interface/ssl/ispserver.key')) {
			$content = str_replace('{ssl_comment}', '', $content);
		} else {
			$content = str_replace('{ssl_comment}', '#', $content);
		}
		
		$vhost_path = $conf['apache']['vhost_conf_dir'].'/ispconfig.vhost';
		$this->write_config_file($vhost_path, $content);
		
    	if (!is_file('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter')) 
    	{
			mkdir('/var/www/php-fcgi-scripts/ispconfig', 0755, true);
			copy('tpl/apache_ispconfig_fcgi_starter.master', '/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter');
			exec('chmod +x /var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter');
			chmod('/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter', 0755);
			symlink($install_dir.'/interface/web', '/var/www/ispconfig');
			exec('chown -R ispconfig:ispconfig /var/www/php-fcgi-scripts/ispconfig');
		}
		
		//* Install the update script
		if (is_file('/usr/local/bin/ispconfig_update_from_svn.sh')) {
			unlink('/usr/local/bin/ispconfig_update_from_svn.sh');
		}
		
		chown($install_dir.'/server/scripts/update_from_svn.sh', 'root');
		chmod($install_dir.'/server/scripts/update_from_svn.sh', 0700);
		chown($install_dir.'/server/scripts/update_from_tgz.sh', 'root');
		chmod($install_dir.'/server/scripts/update_from_tgz.sh', 0700);
		chown($install_dir.'/server/scripts/ispconfig_update.sh', 'root');
		chmod($install_dir.'/server/scripts/ispconfig_update.sh', 0700);
		
		if (!is_link('/usr/local/bin/ispconfig_update_from_svn.sh')) {
			symlink($install_dir.'/server/scripts/ispconfig_update.sh', '/usr/local/bin/ispconfig_update_from_svn.sh');
		}
		
		if (!is_link('/usr/local/bin/ispconfig_update.sh')) {
			symlink($install_dir.'/server/scripts/ispconfig_update.sh', '/usr/local/bin/ispconfig_update.sh');
		}
		
		//* Make the logs readable for the ispconfig user
		if (is_file('/var/log/maillog')) {
			exec('chmod +r /var/log/maillog');
		}
		if (is_file('/var/log/messages')) {
			exec('chmod +r /var/log/messages');
		}
		if (is_file('/var/log/clamav/clamav.log')) {
			exec('chmod +r /var/log/clamav/clamav.log');
		}
		if (is_file('/var/log/clamav/freshclam.log')) {
			exec('chmod +r /var/log/clamav/freshclam.log');
		}
		
		//* Create the ispconfig log directory
		if (!is_dir($conf['ispconfig_log_dir'])) {
			mkdir($conf['ispconfig_log_dir']);
		}
		if (!is_file($conf['ispconfig_log_dir'].'/ispconfig.log')) {
			touch($conf['ispconfig_log_dir'].'/ispconfig.log');
		}
		
		rename($install_dir.'/server/scripts/run-getmail.sh', '/usr/local/bin/run-getmail.sh');
		
		if (is_user('getmail')) {
			chown('/usr/local/bin/run-getmail.sh', 'getmail');
		}
		chmod('/usr/local/bin/run-getmail.sh', 0744);
    }
}

?>
