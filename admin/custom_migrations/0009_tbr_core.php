<?php

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_meetings` (
  `tbr_id` int(11) NOT NULL AUTO_INCREMENT,
  `tbr_client_id` int(11) NOT NULL,
  `tbr_previous_tbr_id` int(11) DEFAULT NULL,
  `tbr_title` varchar(255) NOT NULL,
  `tbr_type` enum('Quarterly','Annual','Special') NOT NULL DEFAULT 'Quarterly',
  `tbr_status` enum('Scheduled','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `tbr_meeting_date` date NOT NULL,
  `tbr_start_time` time NOT NULL DEFAULT '09:00:00',
  `tbr_duration_minutes` int(11) NOT NULL DEFAULT 60,
  `tbr_location` varchar(500) DEFAULT NULL,
  `tbr_assigned_user_id` int(11) DEFAULT NULL,
  `tbr_reporting_period_start` date DEFAULT NULL,
  `tbr_reporting_period_end` date DEFAULT NULL,
  `tbr_additional_emails` text DEFAULT NULL,
  `tbr_prep_notes` longtext DEFAULT NULL,
  `tbr_executive_summary` longtext DEFAULT NULL,
  `tbr_overall_health` enum('Good','Needs Attention','Critical','Not Assessed') NOT NULL DEFAULT 'Not Assessed',
  `tbr_recurring_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `tbr_recurring_interval` enum('None','Quarterly','Annual') NOT NULL DEFAULT 'None',
  `tbr_auto_generate_agenda` tinyint(1) NOT NULL DEFAULT 1,
  `tbr_next_review_date` date DEFAULT NULL,
  `tbr_client_report_approved` tinyint(1) NOT NULL DEFAULT 0,
  `tbr_client_report_approved_by` int(11) DEFAULT NULL,
  `tbr_client_report_approved_at` datetime DEFAULT NULL,
  `tbr_completed_at` datetime DEFAULT NULL,
  `tbr_completed_by` int(11) DEFAULT NULL,
  `tbr_completion_snapshot` longtext DEFAULT NULL,
  `tbr_calendar_event_id` int(11) DEFAULT NULL,
  `tbr_ical_sequence` int(11) NOT NULL DEFAULT 0,
  `tbr_version` int(11) NOT NULL DEFAULT 1,
  `tbr_last_edited_by` int(11) DEFAULT NULL,
  `tbr_created_by` int(11) DEFAULT NULL,
  `tbr_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tbr_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tbr_archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tbr_id`),
  KEY `idx_tbr_client_date` (`tbr_client_id`, `tbr_meeting_date`),
  KEY `idx_tbr_status_date` (`tbr_status`, `tbr_meeting_date`),
  KEY `idx_tbr_assigned_user` (`tbr_assigned_user_id`),
  KEY `idx_tbr_previous` (`tbr_previous_tbr_id`),
  CONSTRAINT `fk_tbr_meetings_client` FOREIGN KEY (`tbr_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_attendees` (
  `attendee_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendee_tbr_id` int(11) NOT NULL,
  `attendee_type` enum('internal','client','external') NOT NULL,
  `attendee_user_id` int(11) DEFAULT NULL,
  `attendee_contact_id` int(11) DEFAULT NULL,
  `attendee_name` varchar(200) DEFAULT NULL,
  `attendee_email` varchar(200) DEFAULT NULL,
  `attendee_receive_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `attendee_attended` tinyint(1) NOT NULL DEFAULT 0,
  `attendee_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendee_id`),
  KEY `idx_tbr_attendee_type` (`attendee_tbr_id`, `attendee_type`),
  CONSTRAINT `fk_tbr_attendees_meeting` FOREIGN KEY (`attendee_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_section_responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `response_tbr_id` int(11) NOT NULL,
  `section_key` varchar(50) NOT NULL,
  `section_status` enum('Not Started','In Progress','Completed','Not Assessed') NOT NULL DEFAULT 'Not Started',
  `section_health` enum('Good','Needs Attention','Critical','Not Assessed') NOT NULL DEFAULT 'Not Assessed',
  `answers_json` longtext DEFAULT NULL,
  `client_notes` longtext DEFAULT NULL,
  `internal_notes` longtext DEFAULT NULL,
  `follow_up_notes` longtext DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`response_id`),
  UNIQUE KEY `uniq_tbr_section` (`response_tbr_id`, `section_key`),
  CONSTRAINT `fk_tbr_responses_meeting` FOREIGN KEY (`response_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_findings` (
  `finding_id` int(11) NOT NULL AUTO_INCREMENT,
  `finding_tbr_id` int(11) NOT NULL,
  `finding_client_id` int(11) NOT NULL,
  `finding_section` varchar(50) NOT NULL,
  `finding_category` varchar(100) DEFAULT NULL,
  `finding_asset_id` int(11) DEFAULT NULL,
  `finding_title` varchar(255) NOT NULL,
  `finding_description` text DEFAULT NULL,
  `finding_business_impact` text DEFAULT NULL,
  `finding_recommended_action` text DEFAULT NULL,
  `finding_risk_level` enum('Low','Moderate','High','Critical') NOT NULL DEFAULT 'Moderate',
  `finding_priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `finding_status` enum('Open','In Progress','Mitigated','Accepted','Resolved') NOT NULL DEFAULT 'Open',
  `finding_immediate_attention` tinyint(1) NOT NULL DEFAULT 0,
  `finding_responsible_party` varchar(150) DEFAULT NULL,
  `finding_target_date` date DEFAULT NULL,
  `finding_estimated_cost` decimal(15,2) DEFAULT NULL,
  `finding_client_visible` tinyint(1) NOT NULL DEFAULT 1,
  `finding_internal_notes` text DEFAULT NULL,
  `finding_created_by` int(11) DEFAULT NULL,
  `finding_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finding_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`finding_id`),
  KEY `idx_tbr_finding_section` (`finding_tbr_id`, `finding_section`),
  KEY `idx_tbr_finding_client` (`finding_client_id`),
  CONSTRAINT `fk_tbr_findings_meeting` FOREIGN KEY (`finding_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_recommendations` (
  `recommendation_id` int(11) NOT NULL AUTO_INCREMENT,
  `recommendation_tbr_id` int(11) NOT NULL,
  `recommendation_client_id` int(11) NOT NULL,
  `recommendation_title` varchar(255) NOT NULL,
  `recommendation_description` text DEFAULT NULL,
  `recommendation_justification` text DEFAULT NULL,
  `recommendation_problem` text DEFAULT NULL,
  `recommendation_risk_addressed` text DEFAULT NULL,
  `recommendation_priority` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `recommendation_estimated_cost` decimal(15,2) DEFAULT NULL,
  `recommendation_timeline` varchar(150) DEFAULT NULL,
  `recommendation_responsible_party` varchar(150) DEFAULT NULL,
  `recommendation_status` enum('Proposed','Approved','Declined','Deferred','Completed') NOT NULL DEFAULT 'Proposed',
  `recommendation_notes` text DEFAULT NULL,
  `recommendation_internal_notes` text DEFAULT NULL,
  `recommendation_carried_from_id` int(11) DEFAULT NULL,
  `recommendation_created_by` int(11) DEFAULT NULL,
  `recommendation_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recommendation_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`recommendation_id`),
  KEY `idx_tbr_rec_tbr` (`recommendation_tbr_id`),
  KEY `idx_tbr_rec_client` (`recommendation_client_id`),
  CONSTRAINT `fk_tbr_recommendations_meeting` FOREIGN KEY (`recommendation_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_roadmap_items` (
  `roadmap_id` int(11) NOT NULL AUTO_INCREMENT,
  `roadmap_tbr_id` int(11) NOT NULL,
  `roadmap_client_id` int(11) NOT NULL,
  `roadmap_recommendation_id` int(11) DEFAULT NULL,
  `roadmap_project_name` varchar(255) NOT NULL,
  `roadmap_description` text DEFAULT NULL,
  `roadmap_category` enum('Required Security','Hardware Replacement','Software & Licensing','Infrastructure Improvement','Optional Enhancement') NOT NULL DEFAULT 'Infrastructure Improvement',
  `roadmap_quarter` enum('Q1','Q2','Q3','Q4') NOT NULL DEFAULT 'Q1',
  `roadmap_fiscal_year` int(11) NOT NULL DEFAULT 2026,
  `roadmap_estimated_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `roadmap_priority` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `roadmap_status` enum('Planned','Approved','In Progress','Completed','Deferred') NOT NULL DEFAULT 'Planned',
  `roadmap_created_by` int(11) DEFAULT NULL,
  `roadmap_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `roadmap_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`roadmap_id`),
  KEY `idx_tbr_roadmap_tbr` (`roadmap_tbr_id`),
  KEY `idx_tbr_roadmap_client` (`roadmap_client_id`),
  CONSTRAINT `fk_tbr_roadmap_meeting` FOREIGN KEY (`roadmap_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_action_items` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `action_tbr_id` int(11) NOT NULL,
  `action_client_id` int(11) NOT NULL,
  `action_description` text NOT NULL,
  `action_responsible_party` enum('MSP','Client','Shared') NOT NULL DEFAULT 'MSP',
  `action_assigned_user_id` int(11) DEFAULT NULL,
  `action_client_owner` varchar(200) DEFAULT NULL,
  `action_priority` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `action_due_date` date DEFAULT NULL,
  `action_status` enum('Not Started','In Progress','Waiting on Client','Completed','Cancelled') NOT NULL DEFAULT 'Not Started',
  `action_internal_notes` text DEFAULT NULL,
  `action_ticket_id` int(11) DEFAULT NULL,
  `action_task_id` int(11) DEFAULT NULL,
  `action_carried_from_id` int(11) DEFAULT NULL,
  `action_completed_at` datetime DEFAULT NULL,
  `action_created_by` int(11) DEFAULT NULL,
  `action_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `action_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`action_id`),
  KEY `idx_tbr_action_tbr` (`action_tbr_id`),
  KEY `idx_tbr_action_client_status` (`action_client_id`, `action_status`),
  CONSTRAINT `fk_tbr_actions_meeting` FOREIGN KEY (`action_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_tbr_id` int(11) NOT NULL,
  `report_client_id` int(11) NOT NULL,
  `report_type` enum('client','internal') NOT NULL DEFAULT 'client',
  `report_version` int(11) NOT NULL DEFAULT 1,
  `report_filename` varchar(255) NOT NULL,
  `report_filepath` varchar(500) NOT NULL,
  `report_filesize` int(11) NOT NULL DEFAULT 0,
  `report_generated_by` int(11) DEFAULT NULL,
  `report_generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `report_emailed_at` datetime DEFAULT NULL,
  `report_emailed_to` text DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_tbr_report_tbr` (`report_tbr_id`),
  KEY `idx_tbr_report_client` (`report_client_id`),
  CONSTRAINT `fk_tbr_reports_meeting` FOREIGN KEY (`report_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_notification_schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_tbr_id` int(11) NOT NULL,
  `schedule_type` enum('scheduled_confirmation','reminder_7d','reminder_1d','reminder_day_of','overdue_alert','action_due_alert','rescheduled','cancelled') NOT NULL,
  `schedule_audience` enum('client','internal') NOT NULL,
  `schedule_send_at` datetime NOT NULL,
  `schedule_status` enum('Pending','Sent','Cancelled','Skipped') NOT NULL DEFAULT 'Pending',
  `schedule_meeting_signature` varchar(64) NOT NULL,
  `schedule_action_item_id` int(11) DEFAULT NULL,
  `schedule_sent_at` datetime DEFAULT NULL,
  `schedule_error` text DEFAULT NULL,
  `schedule_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `idx_tbr_sched_status_at` (`schedule_status`, `schedule_send_at`),
  KEY `idx_tbr_sched_lookup` (`schedule_tbr_id`, `schedule_type`, `schedule_audience`),
  CONSTRAINT `fk_tbr_schedules_meeting` FOREIGN KEY (`schedule_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_audit_history` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_tbr_id` int(11) NOT NULL,
  `audit_client_id` int(11) NOT NULL,
  `audit_action` varchar(100) NOT NULL,
  `audit_details` text DEFAULT NULL,
  `audit_user_id` int(11) DEFAULT NULL,
  `audit_ip` varchar(64) DEFAULT NULL,
  `audit_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `idx_tbr_audit_tbr` (`audit_tbr_id`, `audit_created_at`),
  CONSTRAINT `fk_tbr_audit_meeting` FOREIGN KEY (`audit_tbr_id`) REFERENCES `tbr_meetings` (`tbr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_settings` (
  `setting_id` int(11) NOT NULL DEFAULT 1,
  `enable_confirmation_email` tinyint(1) NOT NULL DEFAULT 1,
  `enable_7d_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `reminder_1_days` int(11) NOT NULL DEFAULT 7,
  `enable_1d_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `reminder_2_days` int(11) NOT NULL DEFAULT 1,
  `enable_day_of_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `day_of_hours_before` int(11) NOT NULL DEFAULT 3,
  `enable_internal_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `enable_overdue_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `enable_action_due_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `action_due_days_before` int(11) NOT NULL DEFAULT 3,
  `send_client_confirmation` tinyint(1) NOT NULL DEFAULT 1,
  `send_client_7d` tinyint(1) NOT NULL DEFAULT 1,
  `send_client_1d` tinyint(1) NOT NULL DEFAULT 1,
  `send_client_day_of` tinyint(1) NOT NULL DEFAULT 0,
  `send_internal_confirmation` tinyint(1) NOT NULL DEFAULT 1,
  `send_internal_7d` tinyint(1) NOT NULL DEFAULT 1,
  `send_internal_1d` tinyint(1) NOT NULL DEFAULT 1,
  `send_internal_day_of` tinyint(1) NOT NULL DEFAULT 1,
  `attach_ics_calendar` tinyint(1) NOT NULL DEFAULT 1,
  `default_duration_minutes` int(11) NOT NULL DEFAULT 60,
  `default_confidentiality_label` varchar(255) NOT NULL DEFAULT 'CONFIDENTIAL - PREPARED EXCLUSIVELY FOR CLIENT LEADERSHIP',
  `tpl_confirmation_subject` varchar(255) NOT NULL,
  `tpl_confirmation_body` longtext NOT NULL,
  `tpl_reminder_subject` varchar(255) NOT NULL,
  `tpl_reminder_body` longtext NOT NULL,
  `tpl_report_subject` varchar(255) NOT NULL,
  `tpl_report_body` longtext NOT NULL,
  `tpl_internal_reminder_subject` varchar(255) NOT NULL,
  `tpl_internal_reminder_body` longtext NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `tbr_role_permissions` (
  `role_id` int(11) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_schedule` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_complete` tinyint(1) NOT NULL DEFAULT 0,
  `can_generate_pdf` tinyint(1) NOT NULL DEFAULT 1,
  `can_email_report` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_internal_notes` tinyint(1) NOT NULL DEFAULT 0,
  `can_reopen` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_settings` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

mysqli_query($mysqli, "ALTER TABLE `email_queue` MODIFY COLUMN `email_cal_str` text DEFAULT NULL;");

$mod_check = mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_tbr' LIMIT 1");
if ($mod_check && mysqli_num_rows($mod_check) === 0) {
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_tbr', module_description = 'Technology Business Review (TBR) Management'");
    $new_module_id = mysqli_insert_id($mysqli);
    if ($new_module_id > 0) {
        mysqli_query($mysqli, "INSERT INTO user_role_permissions (user_role_id, module_id, user_role_permission_level) VALUES (1, $new_module_id, 1), (2, $new_module_id, 2)");
    }
}

mysqli_query($mysqli, "INSERT IGNORE INTO `tbr_role_permissions` (`role_id`, `can_view`, `can_schedule`, `can_edit`, `can_complete`, `can_generate_pdf`, `can_email_report`, `can_view_internal_notes`, `can_reopen`, `can_manage_settings`) VALUES
(1, 1, 0, 0, 0, 1, 0, 0, 0, 0),
(2, 1, 1, 1, 1, 1, 1, 1, 0, 0),
(3, 1, 1, 1, 1, 1, 1, 1, 1, 1);");

require_once dirname(__DIR__, 2) . '/functions/tbr.php';
$tbr_tpls = tbrGetDefaultEmailTemplates();
$tpl_conf_subject = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_confirmation_subject']);
$tpl_conf_body = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_confirmation_body']);
$tpl_rem_subject = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_reminder_subject']);
$tpl_rem_body = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_reminder_body']);
$tpl_rep_subject = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_report_subject']);
$tpl_rep_body = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_report_body']);
$tpl_int_subject = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_internal_reminder_subject']);
$tpl_int_body = mysqli_real_escape_string($mysqli, $tbr_tpls['tpl_internal_reminder_body']);

mysqli_query($mysqli, "INSERT IGNORE INTO `tbr_settings` (
  `setting_id`, `tpl_confirmation_subject`, `tpl_confirmation_body`,
  `tpl_reminder_subject`, `tpl_reminder_body`,
  `tpl_report_subject`, `tpl_report_body`,
  `tpl_internal_reminder_subject`, `tpl_internal_reminder_body`
) VALUES (
  1, '$tpl_conf_subject', '$tpl_conf_body',
  '$tpl_rem_subject', '$tpl_rem_body',
  '$tpl_rep_subject', '$tpl_rep_body',
  '$tpl_int_subject', '$tpl_int_body'
);");
