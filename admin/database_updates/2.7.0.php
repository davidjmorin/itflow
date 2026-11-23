<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings` 
    ADD COLUMN IF NOT EXISTS `config_ticket_client_email_subject` varchar(255) DEFAULT NULL AFTER `config_client_onboarding_email_body`");

mysqli_query($mysqli, "ALTER TABLE `settings` 
    ADD COLUMN IF NOT EXISTS `config_ticket_client_email_body` mediumtext DEFAULT NULL AFTER `config_ticket_client_email_subject`");
