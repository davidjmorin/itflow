<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD COLUMN IF NOT EXISTS `config_tech_weekly_summary_enable` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `config_tech_weekly_summary_time` varchar(10) NOT NULL DEFAULT '08:00',
    ADD COLUMN IF NOT EXISTS `config_tech_weekly_summary_last_sent_week` varchar(20) DEFAULT NULL");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tech_weekly_summary_configs` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `sms_numbers` text DEFAULT NULL,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `extra_emails` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

