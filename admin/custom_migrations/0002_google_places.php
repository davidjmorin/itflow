<?php

/*
 * ITFlow - Database update to version 2.6.9 (from 2.6.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings` 
    ADD COLUMN IF NOT EXISTS `config_google_places_api_key` varchar(255) DEFAULT NULL AFTER `config_whitelabel_key`");
