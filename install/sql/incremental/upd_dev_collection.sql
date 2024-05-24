INSERT IGNORE INTO `dns_ssl_ca` (`id`, `sys_userid`, `sys_groupid`, `sys_perm_user`, `sys_perm_group`, `sys_perm_other`, `active`, `ca_name`, `ca_issue`, `ca_wildcard`, `ca_iodef`, `ca_critical`) VALUES
(NULL, 1, 1, 'riud', 'riud', '', 'Y', 'Amazon Trust Services', 'amazontrust.com', 'Y', '', 0);

-- 5374-mail-last-accessed-frontend
ALTER TABLE `mail_user` ADD `last_access` int(11) NULL DEFAULT NULL after `disabledoveadm`;

ALTER TABLE `web_domain` ADD `disable_symlinknotowner` enum('n','y') NOT NULL default 'n' AFTER `last_jailkit_hash`;
