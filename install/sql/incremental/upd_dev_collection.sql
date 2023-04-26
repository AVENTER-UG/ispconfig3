
ALTER TABLE `mail_user` CHANGE `quota` `quota` BIGINT(20) NOT NULL DEFAULT '0';
ALTER TABLE `server_php` ADD `sortprio` INT(20) NOT NULL DEFAULT '100' AFTER `active`;
