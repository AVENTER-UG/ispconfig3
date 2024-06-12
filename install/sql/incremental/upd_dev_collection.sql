INSERT IGNORE INTO `dns_ssl_ca` (`id`, `sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `active`, `ca_name`, `ca_issue`, `ca_wildcard`, `ca_iodef`, `ca_critical`) VALUES
(NULL, 1, 1, 'riud', 'riud', '', 'Y', 'Amazon Trust Services', 'amazontrust.com', 'Y', '', 0);
ALTER TABLE `web_domain` ADD `disable_symlinknotowner` enum('n','y') NOT NULL default 'n' AFTER `last_jailkit_hash`;
UPDATE `web_domain` SET `backup_format_web` = 'tar_gzip' WHERE  `backup_format_web` = 'rar';
UPDATE `web_domain` SET `backup_format_db` = 'zip' WHERE  `backup_format_db` = 'rar';
