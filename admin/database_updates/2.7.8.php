<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_series` (
  `series_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_name` varchar(255) NOT NULL,
  `series_description` text DEFAULT NULL,
  `facilitator_user_id` int(11) NOT NULL,
  `recurrence_type` enum('One-Time','Daily','Weekdays','Weekly','Bi-Weekly','Monthly','Quarterly') NOT NULL DEFAULT 'Daily',
  `recurrence_days` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `start_time` time NOT NULL DEFAULT '09:00:00',
  `end_time` time NOT NULL DEFAULT '10:00:00',
  `timezone` varchar(64) NOT NULL DEFAULT 'America/New_York',
  `default_period_type` enum('Quarterly','Monthly','Annual') NOT NULL DEFAULT 'Quarterly',
  `visibility` enum('Attendees Only','Company-Wide') NOT NULL DEFAULT 'Attendees Only',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`series_id`),
  KEY `idx_ms_active` (`archived_at`, `visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_attendees` (
  `attendee_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('Facilitator','Member') NOT NULL DEFAULT 'Member',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendee_id`),
  UNIQUE KEY `uniq_series_user` (`series_id`, `user_id`),
  CONSTRAINT `fk_meeting_att_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_occurrences` (
  `occurrence_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `occurrence_date` date NOT NULL,
  `date_override` tinyint(1) NOT NULL DEFAULT 0,
  `period_label` varchar(100) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed') NOT NULL DEFAULT 'In Progress',
  `shared_notes` longtext DEFAULT NULL,
  `shared_notes_updated_by` int(11) DEFAULT NULL,
  `shared_notes_version` int(11) NOT NULL DEFAULT 1,
  `sync_seq` bigint(20) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`occurrence_id`),
  UNIQUE KEY `uniq_series_date` (`series_id`, `occurrence_date`),
  CONSTRAINT `fk_meeting_occ_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_agenda_items` (
  `agenda_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `item_type` enum('prompt_per_user','metrics','action_items','discussion') NOT NULL DEFAULT 'prompt_per_user',
  `icon` varchar(60) NOT NULL DEFAULT 'fas fa-comment-dots',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`agenda_item_id`),
  KEY `idx_agenda_series` (`series_id`, `sort_order`),
  CONSTRAINT `fk_meeting_agenda_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `occurrence_id` int(11) NOT NULL,
  `agenda_item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`response_id`),
  UNIQUE KEY `uniq_occ_item_user` (`occurrence_id`, `agenda_item_id`, `user_id`),
  CONSTRAINT `fk_meeting_resp_occ` FOREIGN KEY (`occurrence_id`) REFERENCES `meeting_occurrences` (`occurrence_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_resp_item` FOREIGN KEY (`agenda_item_id`) REFERENCES `meeting_agenda_items` (`agenda_item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_metrics` (
  `metric_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `metric_name` varchar(200) NOT NULL,
  `unit` enum('Number','Currency','Percent') NOT NULL DEFAULT 'Number',
  `scope` enum('Attendee','Company') NOT NULL DEFAULT 'Attendee',
  `is_starred` tinyint(1) NOT NULL DEFAULT 0,
  `goal_direction` enum('HigherBetter','LowerBetter') NOT NULL DEFAULT 'HigherBetter',
  `period_target` decimal(15,2) NOT NULL DEFAULT 0.00,
  `to_date_target` decimal(15,2) NOT NULL DEFAULT 0.00,
  `target_pace` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`metric_id`),
  KEY `idx_metric_series` (`series_id`, `archived_at`, `sort_order`),
  CONSTRAINT `fk_meeting_metric_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_metric_values` (
  `value_id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_id` int(11) NOT NULL,
  `period_key` varchar(20) NOT NULL,
  `actual_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `to_date_actual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_color` enum('green','yellow','red','gray') NOT NULL DEFAULT 'gray',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`value_id`),
  UNIQUE KEY `uniq_metric_period` (`metric_id`, `period_key`),
  CONSTRAINT `fk_meeting_val_metric` FOREIGN KEY (`metric_id`) REFERENCES `meeting_metrics` (`metric_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_action_items` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `origin_occurrence_id` int(11) DEFAULT NULL,
  `completed_occurrence_id` int(11) DEFAULT NULL,
  `owner_user_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','In Progress','Completed') NOT NULL DEFAULT 'Open',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`action_id`),
  KEY `idx_action_series_status` (`series_id`, `status`),
  CONSTRAINT `fk_meeting_action_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_personal_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `occurrence_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`note_id`),
  UNIQUE KEY `uniq_pnote_occ_user` (`occurrence_id`, `user_id`),
  CONSTRAINT `fk_meeting_pnote_occ` FOREIGN KEY (`occurrence_id`) REFERENCES `meeting_occurrences` (`occurrence_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `occurrence_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(1000) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `idx_attach_series` (`series_id`, `occurrence_id`),
  CONSTRAINT `fk_meeting_attach_series` FOREIGN KEY (`series_id`) REFERENCES `meeting_series` (`series_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `meeting_presence` (
  `occurrence_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `active_agenda_item_id` int(11) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`occurrence_id`, `user_id`),
  CONSTRAINT `fk_meeting_pres_occ` FOREIGN KEY (`occurrence_id`) REFERENCES `meeting_occurrences` (`occurrence_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$mod_check = mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_meetings' LIMIT 1");
if ($mod_check && mysqli_num_rows($mod_check) === 0) {
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_meetings', module_description = 'Metronome-style Collaborative Meetings & Huddles'");
    $new_module_id = mysqli_insert_id($mysqli);
    if ($new_module_id > 0) {
        mysqli_query($mysqli, "INSERT INTO user_role_permissions (user_role_id, module_id, user_role_permission_level) VALUES (1, $new_module_id, 2), (2, $new_module_id, 2)");
    }
}
