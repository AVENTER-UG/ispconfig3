-- add dsgvo and agb support
ALTER TABLE `client` ADD `agb` enum('n','y') NOT NULL default 'n';
ALTER TABLE `client` ADD `dsgvo` enum('n','y') NOT NULL default 'n';

