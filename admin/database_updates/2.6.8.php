<?php

/*
 * ITFlow - Database update to version 2.6.8 (from 2.6.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `credentials` 
    ADD COLUMN IF NOT EXISTS `credential_type` varchar(50) NOT NULL DEFAULT 'Standard' AFTER `credential_description`,
    ADD COLUMN IF NOT EXISTS `credential_wifi_ssid` varchar(200) DEFAULT NULL AFTER `credential_category`,
    ADD COLUMN IF NOT EXISTS `credential_wifi_passcode` varchar(500) DEFAULT NULL AFTER `credential_wifi_ssid`,
    ADD COLUMN IF NOT EXISTS `credential_wifi_encryption` varchar(100) DEFAULT NULL AFTER `credential_wifi_passcode`");
