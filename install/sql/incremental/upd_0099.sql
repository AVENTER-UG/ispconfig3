ALTER TABLE `spamfilter_policy` 
CHANGE `warnvirusrecip` `warnvirusrecip` VARCHAR(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT 'N', 
CHANGE `warnbannedrecip` `warnbannedrecip` VARCHAR(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT 'N', 
CHANGE `warnbadhrecip` `warnbadhrecip` VARCHAR(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT 'N';
ALTER TABLE `sys_ini` CHANGE `default_logo` `default_logo` TEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL;
ALTER TABLE `sys_ini` CHANGE `custom_logo` `custom_logo` TEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL;