ALTER TABLE `mail_user` CHANGE `quota` `quota` BIGINT(20) NOT NULL DEFAULT '0';
-- 5918 add imap_prefix column to mail_user table
ALTER TABLE `mail_user` ADD COLUMN `imap_prefix` varchar(255) NULL default NULL AFTER `backup_copies`;
-- #6456 comodoca.com needs to become sectigo.com
UPDATE `dns_ssl_ca` SET `ca_issue` = 'sectigo.com' WHERE `ca_issue` = 'comodo.com';
UPDATE `dns_ssl_ca` SET `ca_issue` = 'sectigo.com' WHERE `ca_issue` = 'comodoca.com';
UPDATE `dns_ssl_ca` SET `ca_name` = 'Sectigo (formerly Comodo CA)' WHERE `ca_issue` = 'sectigo.com';
-- not updating the dns_rr table to change all CAA records that have comodo.com / comodoca.com - we should not touch users records imo - TP

-- #6445 Update the mailbox_soft_delete config option to it's new structure.
-- UPDATE server SET config=REGEXP_REPLACE(config, 'mailbox_soft_delete=n', 'mailbox_soft_delete=0') WHERE config LIKE '%mailbox_soft_delete=n%'
-- UPDATE server SET config=REGEXP_REPLACE(config, 'mailbox_soft_delete=y', 'mailbox_soft_delete=7') WHERE config LIKE '%mailbox_soft_delete=y%'
