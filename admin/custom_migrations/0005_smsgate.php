<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings` 
    ADD COLUMN IF NOT EXISTS `config_smsgate_enable` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `config_smsgate_url` varchar(255) NOT NULL DEFAULT 'https://api.sms-gate.app/3rdparty/v1/messages',
    ADD COLUMN IF NOT EXISTS `config_smsgate_username` varchar(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `config_smsgate_password` varchar(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `config_smsgate_emergency_recipients` text DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `config_smsgate_ack_message` text DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `config_smsgate_urgent_message` text DEFAULT NULL");
