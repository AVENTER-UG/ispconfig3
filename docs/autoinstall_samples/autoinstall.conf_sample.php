<?php
$autoinstall['language'] = 'en'; // de, en (default)
$autoinstall['install_mode'] = 'standard'; // standard (default), expert

$autoinstall['hostname'] = 'server1.example.com'; // default
$autoinstall['mysql_hostname'] = 'localhost'; // default: localhost
$autoinstall['mysql_port'] = '3306'; // default: 3306
$autoinstall['mysql_root_user'] = 'root'; // default: root
$autoinstall['mysql_root_password'] = 'howtoforge';
$autoinstall['mysql_database'] = 'dbispconfig'; // default: dbispcongig
$autoinstall['mysql_charset'] = 'utf8'; // default: utf8
$autoinstall['http_server'] = 'nginx'; // apache (default), nginx
$autoinstall['ispconfig_port'] = '8080'; // default: 8080
$autoinstall['ispconfig_use_ssl'] = 'y'; // y (default), n
$autoinstall['ispconfig_admin_password'] = 'admin'; // default: admin
$autoinstall['create_ssl_server_certs'] = 'y';
$autoinstall['ignore_hostname_dns'] = 'n';
$autoinstall['ispconfig_postfix_ssl_symlink'] = 'y';
$autoinstall['ispconfig_pureftpd_ssl_symlink'] = 'y';

/* SSL Settings */
$autoinstall['ssl_cert_country'] = 'AU';
$autoinstall['ssl_cert_state'] = 'Some-State';
$autoinstall['ssl_cert_locality'] = 'Chicago';
$autoinstall['ssl_cert_organisation'] = 'Internet Widgits Pty Ltd';
$autoinstall['ssl_cert_organisation_unit'] = 'IT department';
$autoinstall['ssl_cert_common_name'] = $autoinstall['hostname'];
$autoinstall['ssl_cert_email'] = 'hostmaster@'.$autoinstall['hostname'];

/* optional expert mode settings, needed only for expert mode */
$autoinstall['mysql_ispconfig_user'] = 'ispconfig'; // default: ispconfig
$autoinstall['mysql_ispconfig_password'] = bin2hex(random_bytes(20));
$autoinstall['join_multiserver_setup'] = 'n'; // y, n (default)
$autoinstall['mysql_master_hostname'] = 'master.example.com';
$autoinstall['mysql_master_root_user'] = 'root';
$autoinstall['mysql_master_root_password'] = 'howtoforge';
$autoinstall['mysql_master_database'] = 'dbispconfig'; // default: dbispconfig
$autoinstall['configure_mail'] = 'y'; // y (default), n
$autoinstall['configure_jailkit'] = 'y'; // y (default), n
$autoinstall['configure_ftp'] = 'y'; // y (default), n
$autoinstall['configure_dns'] = 'y'; // y (default), n
$autoinstall['configure_apache'] = 'y'; // y (default), n
$autoinstall['configure_nginx'] = 'y'; // y (default), n
$autoinstall['configure_firewall'] = 'y'; // y (default), n
$autoinstall['install_ispconfig_web_interface'] = 'y'; // y (default), n

/* optional update settings, needed only for updates */
$autoupdate['do_backup'] = 'yes'; // yes (default), no
$autoupdate['mysql_root_password'] = 'howtoforge';
$autoupdate['mysql_master_hostname'] = 'master.example.com';
$autoupdate['mysql_master_root_user'] = 'root';
$autoupdate['mysql_master_root_password'] = 'howtoforge';
$autoupdate['mysql_master_database'] = 'dbispconfig'; // default: dbispconfig
$autoupdate['reconfigure_permissions_in_master_database'] = 'no'; // no (default), yes
$autoupdate['reconfigure_services'] = 'yes'; // yes (default), no
$autoupdate['ispconfig_port'] = '8080'; // default: 8080
$autoupdate['create_new_ispconfig_ssl_cert'] = 'no'; // no (default), yes
$autoupdate['reconfigure_crontab'] = 'yes'; // yes (default), no
$autoupdate['create_ssl_server_certs'] = 'y';
$autoupdate['ignore_hostname_dns'] = 'n';
$autoupdate['ispconfig_postfix_ssl_symlink'] = 'y';
$autoupdate['ispconfig_pureftpd_ssl_symlink'] = 'y';

/* These are for service-detection (defaulting to old behaviour where all changes were automatically accepted) */
$autoupdate['svc_detect_change_mail_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_web_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_dns_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_xmpp_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_firewall_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_vserver_server'] = 'yes'; // yes (default), no
$autoupdate['svc_detect_change_db_server'] = 'yes'; // yes (default), no

?>
