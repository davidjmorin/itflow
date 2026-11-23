<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_rules` (
  `ticket_rule_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_rule_order` int(11) NOT NULL DEFAULT 0,
  `ticket_rule_active` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_rule_name` varchar(255) NOT NULL,
  `ticket_rule_match_type` varchar(20) NOT NULL DEFAULT 'all',
  `ticket_rule_sender_email` varchar(255) DEFAULT NULL,
  `ticket_rule_sender_domain` varchar(255) DEFAULT NULL,
  `ticket_rule_subject` varchar(255) DEFAULT NULL,
  `ticket_rule_body` text DEFAULT NULL,
  `ticket_rule_use_regex` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_rule_action` varchar(50) NOT NULL DEFAULT 'route',
  `ticket_rule_client_id` int(11) NOT NULL DEFAULT 0,
  `ticket_rule_priority` varchar(50) DEFAULT NULL,
  `ticket_rule_category` varchar(200) DEFAULT NULL,
  `ticket_rule_assigned_to` int(11) NOT NULL DEFAULT 0,
  `ticket_rule_status` int(11) NOT NULL DEFAULT 1,
  `ticket_rule_stop_processing` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_rule_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ticket_rule_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`ticket_rule_id`),
  KEY `ticket_rule_active_order` (`ticket_rule_active`, `ticket_rule_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
