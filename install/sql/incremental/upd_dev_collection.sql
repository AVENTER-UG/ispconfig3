ALTER TABLE `mail_user` CHANGE `quota` `quota` BIGINT(20) NOT NULL DEFAULT '0';
ALTER TABLE `server_php` ADD `sortprio` INT(20) NOT NULL DEFAULT '100' AFTER `active`;
ALTER TABLE `mail_user` ADD COLUMN `imap_prefix` varchar(255) NULL default NULL AFTER `backup_copies`;
