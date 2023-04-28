ALTER TABLE `mail_user` CHANGE `quota` `quota` BIGINT(20) NOT NULL DEFAULT '0';
-- 5918 add imap_prefix column to mail_user table
ALTER TABLE `mail_user` ADD COLUMN `imap_prefix` varchar(255) NULL default NULL AFTER `backup_copies`;

