<?php

function tbrGetDefaultEmailTemplates(): array {
    return [
        'tpl_confirmation_subject' => 'Technology Business Review Scheduled: {{tbr_title}} ({{meeting_date}})',
        'tpl_confirmation_body' => '<p style="margin:0 0 14px 0;">Hello {{attendee_name}},</p>'
            . '<p style="margin:0 0 18px 0;">Your Technology Business Review with <strong>{{msp_name}}</strong> has been scheduled. Below are your meeting details:</p>'
            . '{{meeting_details_card}}'
            . '<p style="margin:18px 0 0 0;">If you need to reschedule or add attendees, please reply to this email or contact <strong>{{rep_name}}</strong> at <a href="mailto:{{rep_email}}" style="color:#2563eb; text-decoration:none;">{{rep_email}}</a>.</p>'
            . '<p style="margin:18px 0 0 0;">Best regards,<br><strong>{{msp_name}}</strong></p>',
        'tpl_reminder_subject' => 'Reminder: Upcoming Technology Business Review - {{client_name}} on {{meeting_date}}',
        'tpl_reminder_body' => '<p style="margin:0 0 14px 0;">Hello {{attendee_name}},</p>'
            . '<p style="margin:0 0 18px 0;">This is a friendly reminder regarding your upcoming Technology Business Review with <strong>{{msp_name}}</strong>.</p>'
            . '{{meeting_details_card}}'
            . '<p style="margin:18px 0 0 0;">We look forward to reviewing your IT environment, security posture, and technology roadmap together.</p>'
            . '<p style="margin:18px 0 0 0;">Best regards,<br><strong>{{msp_name}}</strong></p>',
        'tpl_report_subject' => 'Technology Business Review Report: {{client_name}} - {{tbr_title}}',
        'tpl_report_body' => '<p style="margin:0 0 14px 0;">Hello {{attendee_name}},</p>'
            . '<p style="margin:0 0 16px 0;">Thank you for participating in your Technology Business Review with <strong>{{msp_name}}</strong>.</p>'
            . '<p style="margin:0 0 18px 0;">Attached to this email is your executive <strong>Technology Business Review Report</strong> covering <strong>{{reporting_period}}</strong>, including your IT environment health summary, support performance metrics, cybersecurity assessment, strategic recommendations, 12-month technology roadmap, and agreed action items.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin:16px 0;">'
            . '<tr><td style="padding:14px 18px; font-size:14px; color:#334155;">'
            . '<strong>Next Scheduled Review:</strong> {{next_review_date}}<br>'
            . '<strong>Account Representative:</strong> {{rep_name}} (<a href="mailto:{{rep_email}}" style="color:#2563eb; text-decoration:none;">{{rep_email}}</a>)'
            . '</td></tr></table>'
            . '<p style="margin:16px 0 0 0;">Please reach out with any questions or feedback.</p>'
            . '<p style="margin:18px 0 0 0;">Best regards,<br><strong>{{msp_name}}</strong></p>',
        'tpl_internal_reminder_subject' => '[Internal TBR Notice] {{tbr_title}} - {{client_name}} ({{meeting_date}})',
        'tpl_internal_reminder_body' => '<p style="margin:0 0 14px 0;">Hello {{attendee_name}},</p>'
            . '<p style="margin:0 0 18px 0;">This is an internal Technology Business Review notification for <strong>{{client_name}}</strong>:</p>'
            . '{{meeting_details_card}}'
            . '<p style="margin:18px 0 0 0;">Please ensure preparation notes, synchronized ITFlow asset/ticket metrics, and prior action items are reviewed ahead of the meeting.</p>',
    ];
}

function tbrGetSettings(): array {
    global $mysqli;

    $tpls = tbrGetDefaultEmailTemplates();
    $defaults = array_merge([
        'setting_id' => 1,
        'enable_confirmation_email' => 1,
        'enable_7d_reminder' => 1,
        'reminder_1_days' => 7,
        'enable_1d_reminder' => 1,
        'reminder_2_days' => 1,
        'enable_day_of_reminder' => 1,
        'day_of_hours_before' => 3,
        'enable_internal_reminders' => 1,
        'enable_overdue_reminders' => 1,
        'enable_action_due_reminders' => 1,
        'action_due_days_before' => 3,
        'send_client_confirmation' => 1,
        'send_client_7d' => 1,
        'send_client_1d' => 1,
        'send_client_day_of' => 0,
        'send_internal_confirmation' => 1,
        'send_internal_7d' => 1,
        'send_internal_1d' => 1,
        'send_internal_day_of' => 1,
        'attach_ics_calendar' => 1,
        'default_duration_minutes' => 60,
        'default_confidentiality_label' => 'CONFIDENTIAL - PREPARED EXCLUSIVELY FOR CLIENT LEADERSHIP',
    ], $tpls);

    $sql = mysqli_query($mysqli, "SELECT * FROM tbr_settings WHERE setting_id = 1 LIMIT 1");
    if ($sql && mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_assoc($sql);
        return array_merge($defaults, $row);
    }

    return $defaults;
}

function tbrHasPermission(string $permission): bool {
    global $mysqli, $session_is_admin, $session_user_role;

    if (!empty($session_is_admin)) {
        return true;
    }

    $module_level = lookupUserPermission('module_tbr');
    if (!$module_level || intval($module_level) < 1) {
        return false;
    }

    $map = [
        'view' => 'can_view',
        'schedule' => 'can_schedule',
        'edit' => 'can_edit',
        'complete' => 'can_complete',
        'generate_pdf' => 'can_generate_pdf',
        'email_report' => 'can_email_report',
        'view_internal_notes' => 'can_view_internal_notes',
        'reopen' => 'can_reopen',
        'manage_settings' => 'can_manage_settings',
    ];

    if (!isset($map[$permission])) {
        return false;
    }

    $role_id = intval($session_user_role ?? 0);
    $res = mysqli_query($mysqli, "SELECT * FROM tbr_role_permissions WHERE role_id = $role_id LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return !empty($row[$map[$permission]]);
    }

    $level = intval($module_level);
    if ($level >= 3) {
        return true;
    }
    if ($level === 2) {
        return in_array($permission, ['view', 'schedule', 'edit', 'complete', 'generate_pdf', 'email_report', 'view_internal_notes'], true);
    }

    return in_array($permission, ['view', 'generate_pdf'], true);
}

function tbrEnforcePermission(string $permission, bool $json = false): void {
    if (tbrHasPermission($permission)) {
        return;
    }

    if ($json) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json');
        }
        echo json_encode(['ok' => false, 'error' => WORDING_ROLECHECK_FAILED]);
        exit();
    }

    $_SESSION['alert_type'] = 'danger';
    $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: $permission access to module_tbr is not permitted for your role.");
}

function tbrCanAccessClient(int $client_id): bool {
    global $mysqli, $session_user_id, $session_is_admin;

    if (!empty($session_is_admin)) {
        return true;
    }

    $client_id = intval($client_id);
    $user_id = intval($session_user_id ?? 0);
    if ($client_id <= 0 || $user_id <= 0) {
        return false;
    }

    $deny_sql = mysqli_query($mysqli, "SELECT client_id FROM user_client_permissions WHERE user_id = $user_id AND client_id = $client_id AND permission_type = 'deny' LIMIT 1");
    if ($deny_sql && mysqli_num_rows($deny_sql) > 0) {
        return false;
    }

    $any_allow_sql = mysqli_query($mysqli, "SELECT client_id FROM user_client_permissions WHERE user_id = $user_id AND permission_type = 'allow' LIMIT 1");
    if ($any_allow_sql && mysqli_num_rows($any_allow_sql) === 0) {
        return true;
    }

    $allow_sql = mysqli_query($mysqli, "SELECT client_id FROM user_client_permissions WHERE user_id = $user_id AND client_id = $client_id AND permission_type = 'allow' LIMIT 1");
    return ($allow_sql && mysqli_num_rows($allow_sql) > 0);
}

function tbrGetSectionsConfig(): array {
    return [
        'sec_01' => [
            'number' => '01',
            'key' => 'sec_01',
            'title' => 'Opening & Business Updates',
            'short_title' => 'Business Updates',
            'icon' => 'fas fa-handshake',
            'required' => true,
            'objective' => "Understand the client's current business operations and upcoming plans.",
            'questions' => [
                'q_business_since_last' => 'How has business been since our last meeting?',
                'q_staffing_changes' => 'Have there been any staffing changes?',
                'q_upcoming_hires_departures' => 'Are there any upcoming hires or employee departures?',
                'q_new_locations_expansion' => 'Are you opening new locations or expanding operations?',
                'q_upcoming_initiatives' => 'Are there any upcoming business initiatives?',
                'q_productivity_issues' => 'Are employees experiencing technology-related productivity issues?',
                'q_business_challenges' => 'Are there any business challenges we should be aware of?',
            ],
            'fields' => [
                'business_updates' => 'Business Updates',
                'staffing_changes' => 'Staffing Changes',
                'upcoming_projects' => 'Upcoming Projects',
                'operational_concerns' => 'Operational Concerns',
                'additional_notes' => 'Additional Notes',
            ],
        ],
        'sec_02' => [
            'number' => '02',
            'key' => 'sec_02',
            'title' => 'IT Environment Overview',
            'short_title' => 'IT Environment',
            'icon' => 'fas fa-server',
            'required' => true,
            'objective' => "Review the overall health of the client's IT infrastructure.",
            'categories' => [
                'workstations' => 'Total Workstations',
                'workstation_os' => 'Workstation Operating Systems',
                'device_lifecycle' => 'Device Ages & Lifecycle',
                'servers_infra' => 'Servers & Infrastructure',
                'network_equipment' => 'Network Equipment',
                'firewall_status' => 'Firewall Status',
                'm365_licensing' => 'Microsoft 365 Users & Licenses',
                'cloud_services' => 'Cloud Services',
                'email_infra' => 'Email Infrastructure',
                'backup_coverage' => 'Backup Coverage',
                'endpoint_security' => 'Endpoint Security Coverage',
                'remote_access' => 'Remote Access Infrastructure',
            ],
        ],
        'sec_03' => [
            'number' => '03',
            'key' => 'sec_03',
            'title' => 'Helpdesk & Support Performance',
            'short_title' => 'Helpdesk & Support',
            'icon' => 'fas fa-life-ring',
            'required' => true,
            'objective' => 'Demonstrate the effectiveness of MSP support over the reporting period.',
            'fields' => [
                'client_feedback' => 'Client Feedback',
                'recurring_problems' => 'Recurring Problems',
                'service_improvements' => 'Service Improvement Opportunities',
                'additional_comments' => 'Additional Comments & Trend Explanations',
            ],
        ],
        'sec_04' => [
            'number' => '04',
            'key' => 'sec_04',
            'title' => 'Cybersecurity Review',
            'short_title' => 'Cybersecurity',
            'icon' => 'fas fa-shield-alt',
            'required' => true,
            'objective' => "Review the client's security posture and identify risks.",
            'areas' => [
                'endpoint_protection' => 'Endpoint Protection Coverage',
                'detected_threats' => 'Detected Security Threats',
                'security_incidents' => 'Security Incidents',
                'phishing_attempts' => 'Phishing Attempts',
                'email_security' => 'Email Security (SPF/DKIM/DMARC/Filtering)',
                'mfa_coverage' => 'MFA Coverage',
                'm365_security' => 'Microsoft 365 Security Configuration',
                'admin_accounts' => 'Administrative Accounts',
                'user_permissions' => 'User Permissions & Least Privilege',
                'app_install_restrictions' => 'Application Installation Restrictions',
                'firewall_security' => 'Firewall Security & Rules',
                'vulnerability_mgmt' => 'Vulnerability Management',
                'patch_mgmt' => 'Patch Management',
                'security_training' => 'Security Awareness Training',
            ],
        ],
        'sec_05' => [
            'number' => '05',
            'key' => 'sec_05',
            'title' => 'Backup & Disaster Recovery',
            'short_title' => 'Backup & DR',
            'icon' => 'fas fa-hdd',
            'required' => true,
            'objective' => "Confirm the client's ability to recover from data loss or a disaster.",
            'metrics' => [
                'backup_success_rate' => 'Backup Success Rate',
                'backup_failure_rate' => 'Backup Failure Rate',
                'last_successful_backup' => 'Last Successful Backup',
                'backup_coverage' => 'Backup Coverage',
                'backup_retention' => 'Backup Retention',
                'offsite_backup_status' => 'Offsite Backup Status',
                'immutable_backup_status' => 'Immutable Backup Status',
                'rpo' => 'Recovery Point Objective (RPO)',
                'rto' => 'Recovery Time Objective (RTO)',
                'last_restoration_test' => 'Last Successful Restoration Test',
                'dr_plan_status' => 'Disaster Recovery Plan Status',
            ],
            'questions' => [
                'q_server_fail_recovery' => 'If the primary server failed today, how long would recovery take?',
                'q_ransomware_recovery' => 'Could we recover from a ransomware attack?',
                'q_tested_restore' => 'Have we tested restoring critical business data?',
                'q_deletion_protection' => 'Are backups protected against deletion or encryption?',
                'q_expectations_documented' => 'Are recovery expectations documented?',
            ],
            'fields' => [
                'backup_findings' => 'Backup Findings',
                'recovery_concerns' => 'Recovery Concerns',
                'restoration_test_results' => 'Restoration Test Results',
                'recommended_improvements' => 'Recommended Improvements',
                'additional_notes' => 'Additional Notes',
            ],
        ],
        'sec_06' => [
            'number' => '06',
            'key' => 'sec_06',
            'title' => 'Hardware & Software Lifecycle',
            'short_title' => 'Lifecycle',
            'icon' => 'fas fa-laptop',
            'required' => true,
            'objective' => 'Identify aging equipment, warranty expirations, and upcoming technology expenses.',
        ],
        'sec_07' => [
            'number' => '07',
            'key' => 'sec_07',
            'title' => 'Strategic IT Recommendations',
            'short_title' => 'Recommendations',
            'icon' => 'fas fa-lightbulb',
            'required' => true,
            'objective' => 'Present technology improvements based on actual business needs.',
        ],
        'sec_08' => [
            'number' => '08',
            'key' => 'sec_08',
            'title' => 'Budget & Technology Roadmap',
            'short_title' => 'Budget & Roadmap',
            'icon' => 'fas fa-road',
            'required' => false,
            'objective' => 'Help clients plan technology spending over the next 12 months.',
        ],
        'sec_09' => [
            'number' => '09',
            'key' => 'sec_09',
            'title' => 'Open Discussion & Client Feedback',
            'short_title' => 'Client Feedback',
            'icon' => 'fas fa-comments',
            'required' => false,
            'objective' => 'Capture open dialogue, client satisfaction, and service improvement requests.',
            'questions' => [
                'q_unaddressed_concerns' => 'Are there any technology concerns we haven\'t addressed?',
                'q_recurring_frustrations' => 'Are employees experiencing recurring frustrations?',
                'q_upcoming_projects' => 'Are there upcoming business projects we should prepare for?',
                'q_msp_improvements' => 'Is there anything you would like our MSP to improve?',
                'q_satisfied_services' => 'Are you satisfied with our current IT services?',
            ],
            'fields' => [
                'client_feedback' => 'Client Feedback',
                'additional_concerns' => 'Additional Concerns',
                'requested_improvements' => 'Requested Improvements',
                'discussion_notes' => 'General Discussion Notes',
            ],
        ],
        'sec_10' => [
            'number' => '10',
            'key' => 'sec_10',
            'title' => 'Action Items & Next Steps',
            'short_title' => 'Action Items',
            'icon' => 'fas fa-tasks',
            'required' => true,
            'objective' => 'Establish clear ownership, deadlines, and follow-up tasks from this review.',
        ],
    ];
}

function tbrGetMeeting(int $tbr_id): ?array {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    if ($tbr_id <= 0) {
        return null;
    }

    $sql = mysqli_query($mysqli, "
        SELECT m.*, c.client_name, c.client_type, c.client_website, c.client_currency_code,
               u.user_name AS rep_name, u.user_email AS rep_email,
               cu.user_name AS completed_by_name,
               au.user_name AS approved_by_name
        FROM tbr_meetings m
        LEFT JOIN clients c ON m.tbr_client_id = c.client_id
        LEFT JOIN users u ON m.tbr_assigned_user_id = u.user_id
        LEFT JOIN users cu ON m.tbr_completed_by = cu.user_id
        LEFT JOIN users au ON m.tbr_client_report_approved_by = au.user_id
        WHERE m.tbr_id = $tbr_id AND m.tbr_archived_at IS NULL
        LIMIT 1
    ");

    if ($sql && mysqli_num_rows($sql) > 0) {
        return mysqli_fetch_assoc($sql);
    }

    return null;
}

function tbrFindPreviousMeeting(int $client_id, ?int $exclude_tbr_id = null, ?string $before_date = null): ?array {
    global $mysqli;

    $client_id = intval($client_id);
    $exclude_sql = $exclude_tbr_id ? " AND tbr_id != " . intval($exclude_tbr_id) : "";
    $date_sql = $before_date ? " AND tbr_meeting_date <= '" . escapeSql($before_date) . "'" : "";

    $sql = mysqli_query($mysqli, "
        SELECT * FROM tbr_meetings
        WHERE tbr_client_id = $client_id
          AND tbr_archived_at IS NULL
          AND tbr_status != 'Cancelled'
          $exclude_sql
          $date_sql
        ORDER BY (tbr_status = 'Completed') DESC, tbr_meeting_date DESC, tbr_id DESC
        LIMIT 1
    ");

    if ($sql && mysqli_num_rows($sql) > 0) {
        return mysqli_fetch_assoc($sql);
    }

    return null;
}

function tbrInitializeAgenda(int $tbr_id, int $user_id = 0): void {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    $user_id = intval($user_id);
    $sections = tbrGetSectionsConfig();

    foreach ($sections as $section_key => $cfg) {
        $key_esc = escapeSql($section_key);
        $default_answers = [];
        if ($section_key === 'sec_02' && !empty($cfg['categories'])) {
            $cats = [];
            foreach ($cfg['categories'] as $cat_key => $cat_label) {
                $cats[$cat_key] = [
                    'status' => '',
                    'health' => 'Not Assessed',
                    'findings' => '',
                    'issues' => '',
                    'recommendations' => '',
                    'notes' => '',
                ];
            }
            $default_answers['categories'] = $cats;
        }
        $answers_esc = escapeSql(json_encode($default_answers));
        mysqli_query($mysqli, "
            INSERT IGNORE INTO tbr_section_responses
            SET response_tbr_id = $tbr_id,
                section_key = '$key_esc',
                section_status = 'Not Started',
                section_health = 'Not Assessed',
                answers_json = '$answers_esc',
                updated_by = $user_id
        ");
    }
}

function tbrCarryForwardFromPrevious(int $tbr_id, int $previous_tbr_id, int $user_id, bool $include_actions = true, bool $include_recommendations = false): array {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    $previous_tbr_id = intval($previous_tbr_id);
    $user_id = intval($user_id);

    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting || $previous_tbr_id <= 0) {
        return ['actions_carried' => 0, 'recommendations_carried' => 0];
    }

    $client_id = intval($meeting['tbr_client_id']);
    $actions_carried = 0;
    $recommendations_carried = 0;

    if ($include_actions) {
        $sql = mysqli_query($mysqli, "
            SELECT * FROM tbr_action_items
            WHERE action_tbr_id = $previous_tbr_id
              AND action_status IN ('Not Started', 'In Progress', 'Waiting on Client')
            ORDER BY action_id ASC
        ");
        while ($sql && ($row = mysqli_fetch_assoc($sql))) {
            $orig_id = intval($row['action_id']);
            $dup = mysqli_query($mysqli, "SELECT action_id FROM tbr_action_items WHERE action_tbr_id = $tbr_id AND action_carried_from_id = $orig_id LIMIT 1");
            if ($dup && mysqli_num_rows($dup) > 0) {
                continue;
            }
            $desc = mysqli_real_escape_string($mysqli, $row['action_description']);
            $party = escapeSql($row['action_responsible_party']);
            $assigned_uid = !empty($row['action_assigned_user_id']) ? intval($row['action_assigned_user_id']) : 'NULL';
            $client_owner = escapeSql($row['action_client_owner'] ?? '');
            $priority = escapeSql($row['action_priority']);
            $due_date = !empty($row['action_due_date']) ? "'" . escapeSql($row['action_due_date']) . "'" : 'NULL';
            $status = escapeSql($row['action_status']);
            $int_notes = mysqli_real_escape_string($mysqli, $row['action_internal_notes'] ?? '');
            $ticket_id = !empty($row['action_ticket_id']) ? intval($row['action_ticket_id']) : 'NULL';
            $task_id = !empty($row['action_task_id']) ? intval($row['action_task_id']) : 'NULL';

            mysqli_query($mysqli, "
                INSERT INTO tbr_action_items SET
                    action_tbr_id = $tbr_id,
                    action_client_id = $client_id,
                    action_description = '$desc',
                    action_responsible_party = '$party',
                    action_assigned_user_id = $assigned_uid,
                    action_client_owner = '$client_owner',
                    action_priority = '$priority',
                    action_due_date = $due_date,
                    action_status = '$status',
                    action_internal_notes = '$int_notes',
                    action_ticket_id = $ticket_id,
                    action_task_id = $task_id,
                    action_carried_from_id = $orig_id,
                    action_created_by = $user_id
            ");
            if (mysqli_insert_id($mysqli) > 0) {
                $actions_carried++;
            }
        }
    }

    if ($include_recommendations) {
        $sql = mysqli_query($mysqli, "
            SELECT * FROM tbr_recommendations
            WHERE recommendation_tbr_id = $previous_tbr_id
              AND recommendation_status IN ('Proposed', 'Deferred')
            ORDER BY recommendation_id ASC
        ");
        while ($sql && ($row = mysqli_fetch_assoc($sql))) {
            $orig_id = intval($row['recommendation_id']);
            $dup = mysqli_query($mysqli, "SELECT recommendation_id FROM tbr_recommendations WHERE recommendation_tbr_id = $tbr_id AND recommendation_carried_from_id = $orig_id LIMIT 1");
            if ($dup && mysqli_num_rows($dup) > 0) {
                continue;
            }
            $title = mysqli_real_escape_string($mysqli, $row['recommendation_title']);
            $desc = mysqli_real_escape_string($mysqli, $row['recommendation_description'] ?? '');
            $just = mysqli_real_escape_string($mysqli, $row['recommendation_justification'] ?? '');
            $prob = mysqli_real_escape_string($mysqli, $row['recommendation_problem'] ?? '');
            $risk = mysqli_real_escape_string($mysqli, $row['recommendation_risk_addressed'] ?? '');
            $prio = escapeSql($row['recommendation_priority']);
            $cost = $row['recommendation_estimated_cost'] !== null ? floatval($row['recommendation_estimated_cost']) : 'NULL';
            $timeline = escapeSql($row['recommendation_timeline'] ?? '');
            $party = escapeSql($row['recommendation_responsible_party'] ?? '');
            $status = escapeSql($row['recommendation_status']);
            $notes = mysqli_real_escape_string($mysqli, $row['recommendation_notes'] ?? '');
            $int_notes = mysqli_real_escape_string($mysqli, $row['recommendation_internal_notes'] ?? '');

            mysqli_query($mysqli, "
                INSERT INTO tbr_recommendations SET
                    recommendation_tbr_id = $tbr_id,
                    recommendation_client_id = $client_id,
                    recommendation_title = '$title',
                    recommendation_description = '$desc',
                    recommendation_justification = '$just',
                    recommendation_problem = '$prob',
                    recommendation_risk_addressed = '$risk',
                    recommendation_priority = '$prio',
                    recommendation_estimated_cost = $cost,
                    recommendation_timeline = '$timeline',
                    recommendation_responsible_party = '$party',
                    recommendation_status = '$status',
                    recommendation_notes = '$notes',
                    recommendation_internal_notes = '$int_notes',
                    recommendation_carried_from_id = $orig_id,
                    recommendation_created_by = $user_id
            ");
            if (mysqli_insert_id($mysqli) > 0) {
                $recommendations_carried++;
            }
        }
    }

    if ($actions_carried > 0 || $recommendations_carried > 0) {
        tbrLogAudit($tbr_id, $client_id, 'Carry Forward', "Carried forward $actions_carried action item(s) and $recommendations_carried recommendation(s) from TBR #$previous_tbr_id", $user_id);
    }

    return ['actions_carried' => $actions_carried, 'recommendations_carried' => $recommendations_carried];
}

function tbrFetchLiveClientData(int $client_id, ?string $period_start = null, ?string $period_end = null): array {
    global $mysqli;

    $client_id = intval($client_id);
    if (empty($period_end) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end)) {
        $period_end = date('Y-m-d');
    }
    if (empty($period_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start)) {
        $period_start = date('Y-m-d', strtotime($period_end . ' -90 days'));
    }

    $today = date('Y-m-d');
    $soon_cutoff = date('Y-m-d', strtotime('+90 days'));

    $assets = [];
    $asset_counts = [
        'total' => 0,
        'workstations' => 0,
        'laptops' => 0,
        'desktops' => 0,
        'servers' => 0,
        'network' => 0,
        'firewalls' => 0,
        'switches' => 0,
        'access_points' => 0,
        'printers' => 0,
        'other' => 0,
        'warranty_expired' => 0,
        'warranty_expiring_90d' => 0,
        'aging_4_plus_years' => 0,
    ];
    $os_breakdown = [];

    $res_assets = mysqli_query($mysqli, "
        SELECT asset_id, asset_type, asset_name, asset_make, asset_model, asset_serial, asset_os,
               asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date,
               l.location_name, c.contact_name
        FROM assets a
        LEFT JOIN locations l ON a.asset_location_id = l.location_id
        LEFT JOIN contacts c ON a.asset_contact_id = c.contact_id
        WHERE a.asset_client_id = $client_id AND a.asset_archived_at IS NULL
        ORDER BY a.asset_type ASC, a.asset_name ASC
    ");
    while ($res_assets && ($a = mysqli_fetch_assoc($res_assets))) {
        $asset_counts['total']++;
        $type_lc = strtolower((string)$a['asset_type']);
        if (str_contains($type_lc, 'laptop')) {
            $asset_counts['laptops']++;
            $asset_counts['workstations']++;
        } elseif (str_contains($type_lc, 'desktop') || str_contains($type_lc, 'workstation')) {
            $asset_counts['desktops']++;
            $asset_counts['workstations']++;
        } elseif (str_contains($type_lc, 'server') || str_contains($type_lc, 'virtual') || str_contains($type_lc, 'hypervisor')) {
            $asset_counts['servers']++;
        } elseif (str_contains($type_lc, 'firewall') || str_contains($type_lc, 'router')) {
            $asset_counts['firewalls']++;
            $asset_counts['network']++;
        } elseif (str_contains($type_lc, 'switch')) {
            $asset_counts['switches']++;
            $asset_counts['network']++;
        } elseif (str_contains($type_lc, 'access point') || str_contains($type_lc, 'wifi') || str_contains($type_lc, 'wireless')) {
            $asset_counts['access_points']++;
            $asset_counts['network']++;
        } elseif (str_contains($type_lc, 'printer')) {
            $asset_counts['printers']++;
        } else {
            $asset_counts['other']++;
        }

        $os_label = trim((string)($a['asset_os'] ?? ''));
        if ($os_label === '') {
            $os_label = 'Unspecified';
        }
        $os_breakdown[$os_label] = ($os_breakdown[$os_label] ?? 0) + 1;

        $ref_date = $a['asset_purchase_date'] ?: $a['asset_install_date'];
        $age_years = null;
        if (!empty($ref_date) && $ref_date !== '0000-00-00') {
            $ts = strtotime($ref_date);
            if ($ts !== false) {
                $age_years = round((time() - $ts) / (365.25 * 86400), 1);
                if ($age_years >= 4.0) {
                    $asset_counts['aging_4_plus_years']++;
                }
            }
        }

        $warranty_status = 'Unknown';
        $w_date = $a['asset_warranty_expire'];
        if (!empty($w_date) && $w_date !== '0000-00-00') {
            if ($w_date < $today) {
                $warranty_status = 'Expired';
                $asset_counts['warranty_expired']++;
            } elseif ($w_date <= $soon_cutoff) {
                $warranty_status = 'Expiring Soon';
                $asset_counts['warranty_expiring_90d']++;
            } else {
                $warranty_status = 'Active';
            }
        }

        $a['age_years'] = $age_years;
        $a['warranty_status'] = $warranty_status;
        $assets[] = $a;
    }
    arsort($os_breakdown);

    $software = [];
    $software_counts = ['total' => 0, 'total_seats' => 0, 'expired' => 0, 'expiring_90d' => 0];
    $res_sw = mysqli_query($mysqli, "
        SELECT software_id, software_name, software_version, software_type, software_license_type,
               software_seats, software_purchase, software_expire
        FROM software
        WHERE software_client_id = $client_id AND software_archived_at IS NULL
        ORDER BY software_name ASC
    ");
    while ($res_sw && ($s = mysqli_fetch_assoc($res_sw))) {
        $software_counts['total']++;
        $software_counts['total_seats'] += intval($s['software_seats'] ?? 0);
        $sw_status = 'Active';
        if (!empty($s['software_expire']) && $s['software_expire'] !== '0000-00-00') {
            if ($s['software_expire'] < $today) {
                $sw_status = 'Expired';
                $software_counts['expired']++;
            } elseif ($s['software_expire'] <= $soon_cutoff) {
                $sw_status = 'Expiring Soon';
                $software_counts['expiring_90d']++;
            }
        } else {
            $sw_status = 'No Expiry';
        }
        $s['license_status'] = $sw_status;
        $software[] = $s;
    }

    $services = [];
    $backup_services = [];
    $res_srv = mysqli_query($mysqli, "
        SELECT service_id, service_name, service_description, service_category, service_importance, service_backup
        FROM services
        WHERE service_client_id = $client_id
        ORDER BY service_name ASC
    ");
    while ($res_srv && ($srv = mysqli_fetch_assoc($res_srv))) {
        $services[] = $srv;
        if (!empty($srv['service_backup'])) {
            $backup_services[] = $srv;
        }
    }

    $networks_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL"))[0] ?? 0);
    $locations_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL"))[0] ?? 0);
    $contacts_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL"))[0] ?? 0);
    $domains_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL"))[0] ?? 0);
    $domains_expiring = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL AND domain_expire IS NOT NULL AND domain_expire <= '$soon_cutoff'"))[0] ?? 0);
    $certs_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL"))[0] ?? 0);
    $certs_expiring = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL AND certificate_expire IS NOT NULL AND certificate_expire <= '$soon_cutoff'"))[0] ?? 0);

    $rmm_info = ['configured' => false, 'mapped' => false, 'last_sync_at' => null];
    $res_rmm = mysqli_query($mysqli, "SELECT client_mapping, last_sync_at FROM integration_nable_rmm ORDER BY id DESC LIMIT 1");
    if ($res_rmm && ($rmm_row = mysqli_fetch_assoc($res_rmm))) {
        $rmm_info['configured'] = true;
        $rmm_info['last_sync_at'] = $rmm_row['last_sync_at'];
        $mapping = json_decode((string)$rmm_row['client_mapping'], true);
        if (is_array($mapping)) {
            foreach ($mapping as $mapped_client) {
                if (intval($mapped_client) === $client_id || (is_array($mapped_client) && intval($mapped_client['itflow_client_id'] ?? 0) === $client_id)) {
                    $rmm_info['mapped'] = true;
                    break;
                }
            }
        }
    }

    $p_start_esc = escapeSql($period_start . ' 00:00:00');
    $p_end_esc = escapeSql($period_end . ' 23:59:59');

    $tickets_opened = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tickets
        WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
          AND ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
    "))[0] ?? 0);

    $tickets_resolved = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tickets
        WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
          AND (
              (ticket_resolved_at IS NOT NULL AND ticket_resolved_at BETWEEN '$p_start_esc' AND '$p_end_esc')
              OR (ticket_resolved_at IS NULL AND ticket_closed_at IS NOT NULL AND ticket_closed_at BETWEEN '$p_start_esc' AND '$p_end_esc')
          )
    "))[0] ?? 0);

    $tickets_outstanding = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tickets
        WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
          AND ticket_closed_at IS NULL AND ticket_status NOT IN (4, 5)
    "))[0] ?? 0);

    $timing_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT
            AVG(CASE WHEN ticket_first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, ticket_created_at, ticket_first_response_at) END) AS avg_resp_min,
            COUNT(CASE WHEN ticket_first_response_at IS NOT NULL THEN 1 END) AS resp_sample,
            AVG(CASE WHEN COALESCE(ticket_resolved_at, ticket_closed_at) IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, ticket_created_at, COALESCE(ticket_resolved_at, ticket_closed_at)) END) AS avg_res_min,
            COUNT(CASE WHEN COALESCE(ticket_resolved_at, ticket_closed_at) IS NOT NULL THEN 1 END) AS res_sample,
            SUM(CASE WHEN ticket_response_sla_met = 1 THEN 1 ELSE 0 END) AS sla_resp_met,
            COUNT(CASE WHEN ticket_response_sla_met IS NOT NULL THEN 1 ELSE 0 END) AS sla_resp_total,
            SUM(CASE WHEN ticket_resolution_sla_met = 1 THEN 1 ELSE 0 END) AS sla_res_met,
            COUNT(CASE WHEN ticket_resolution_sla_met IS NOT NULL THEN 1 ELSE 0 END) AS sla_res_total
        FROM tickets
        WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
          AND ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
    "));

    $sla_resp_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT
            SUM(CASE WHEN ticket_response_sla_met = 1 THEN 1 ELSE 0 END) AS resp_met,
            COUNT(ticket_response_sla_met) AS resp_total,
            SUM(CASE WHEN ticket_resolution_sla_met = 1 THEN 1 ELSE 0 END) AS res_met,
            COUNT(ticket_resolution_sla_met) AS res_total
        FROM tickets
        WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL
          AND ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
    "));

    $sla_response_pct = intval($sla_resp_row['resp_total'] ?? 0) > 0
        ? round((intval($sla_resp_row['resp_met']) / intval($sla_resp_row['resp_total'])) * 100, 1)
        : null;
    $sla_resolution_pct = intval($sla_resp_row['res_total'] ?? 0) > 0
        ? round((intval($sla_resp_row['res_met']) / intval($sla_resp_row['res_total'])) * 100, 1)
        : null;

    $top_categories = [];
    $res_cats = mysqli_query($mysqli, "
        SELECT COALESCE(c.category_name, NULLIF(t.ticket_category, ''), 'Uncategorized') AS category_label, COUNT(*) AS cnt
        FROM tickets t
        LEFT JOIN categories c ON CAST(t.ticket_category AS CHAR) = CAST(c.category_id AS CHAR)
        WHERE t.ticket_client_id = $client_id AND t.ticket_archived_at IS NULL
          AND t.ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
        GROUP BY category_label
        ORDER BY cnt DESC
        LIMIT 8
    ");
    while ($res_cats && ($cr = mysqli_fetch_assoc($res_cats))) {
        $top_categories[] = ['category' => $cr['category_label'], 'count' => intval($cr['cnt'])];
    }

    $recurring_issues = [];
    $res_rec_assets = mysqli_query($mysqli, "
        SELECT a.asset_name, COUNT(*) AS cnt
        FROM tickets t
        JOIN assets a ON t.ticket_asset_id = a.asset_id
        WHERE t.ticket_client_id = $client_id AND t.ticket_archived_at IS NULL
          AND t.ticket_asset_id > 0
          AND t.ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
        GROUP BY t.ticket_asset_id, a.asset_name
        HAVING cnt >= 2
        ORDER BY cnt DESC
        LIMIT 5
    ");
    while ($res_rec_assets && ($ra = mysqli_fetch_assoc($res_rec_assets))) {
        $recurring_issues[] = ['type' => 'Asset', 'label' => $ra['asset_name'], 'count' => intval($ra['cnt'])];
    }
    $res_rec_subj = mysqli_query($mysqli, "
        SELECT t.ticket_subject, COUNT(*) AS cnt
        FROM tickets t
        WHERE t.ticket_client_id = $client_id AND t.ticket_archived_at IS NULL
          AND t.ticket_created_at BETWEEN '$p_start_esc' AND '$p_end_esc'
        GROUP BY t.ticket_subject
        HAVING cnt >= 2
        ORDER BY cnt DESC
        LIMIT 5
    ");
    while ($res_rec_subj && ($rs = mysqli_fetch_assoc($res_rec_subj))) {
        $recurring_issues[] = ['type' => 'Subject', 'label' => $rs['ticket_subject'], 'count' => intval($rs['cnt'])];
    }

    return [
        'period_start' => $period_start,
        'period_end' => $period_end,
        'infrastructure' => [
            'synced' => true,
            'source' => 'ITFlow Assets, Software, Services, Networks, Domains & Certificates',
            'asset_counts' => $asset_counts,
            'os_breakdown' => $os_breakdown,
            'assets' => $assets,
            'software_counts' => $software_counts,
            'software' => $software,
            'services' => $services,
            'backup_services' => $backup_services,
            'networks_count' => $networks_count,
            'locations_count' => $locations_count,
            'contacts_count' => $contacts_count,
            'domains_count' => $domains_count,
            'domains_expiring' => $domains_expiring,
            'certs_count' => $certs_count,
            'certs_expiring' => $certs_expiring,
            'rmm' => $rmm_info,
            'm365_integrated' => false,
            'endpoint_security_integrated' => false,
        ],
        'helpdesk' => [
            'synced' => true,
            'source' => 'ITFlow Ticketing & SLAs',
            'tickets_opened' => $tickets_opened,
            'tickets_resolved' => $tickets_resolved,
            'tickets_outstanding' => $tickets_outstanding,
            'avg_response_minutes' => $timing_row['avg_resp_min'] !== null ? round(floatval($timing_row['avg_resp_min']), 1) : null,
            'response_sample_size' => intval($timing_row['resp_sample'] ?? 0),
            'avg_resolution_minutes' => $timing_row['avg_res_min'] !== null ? round(floatval($timing_row['avg_res_min']), 1) : null,
            'resolution_sample_size' => intval($timing_row['res_sample'] ?? 0),
            'sla_response_compliance_pct' => $sla_response_pct,
            'sla_resolution_compliance_pct' => $sla_resolution_pct,
            'top_categories' => $top_categories,
            'recurring_issues' => $recurring_issues,
        ],
    ];
}

function tbrFormatMinutes(?float $minutes): string {
    if ($minutes === null) {
        return 'N/A';
    }
    $mins = intval(round($minutes));
    if ($mins < 60) {
        return $mins . 'm';
    }
    $hours = floor($mins / 60);
    $rem = $mins % 60;
    if ($hours < 24) {
        return $hours . 'h ' . $rem . 'm';
    }
    $days = floor($hours / 24);
    $rem_h = $hours % 24;
    return $days . 'd ' . $rem_h . 'h';
}

function tbrCalculateProgress(int $tbr_id): array {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    $sections = tbrGetSectionsConfig();
    $total = count($sections);
    $completed = 0;
    $in_progress = 0;
    $not_assessed = 0;
    $statuses = [];

    $res = mysqli_query($mysqli, "SELECT section_key, section_status, section_health, version FROM tbr_section_responses WHERE response_tbr_id = $tbr_id");
    $rows = [];
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $rows[$r['section_key']] = $r;
    }

    foreach ($sections as $key => $cfg) {
        $st = $rows[$key]['section_status'] ?? 'Not Started';
        $hl = $rows[$key]['section_health'] ?? 'Not Assessed';
        $ver = intval($rows[$key]['version'] ?? 1);
        $statuses[$key] = ['status' => $st, 'health' => $hl, 'version' => $ver];
        if ($st === 'Completed') {
            $completed++;
        } elseif ($st === 'Not Assessed') {
            $not_assessed++;
        } elseif ($st === 'In Progress') {
            $in_progress++;
        }
    }

    $done = $completed + $not_assessed;
    $pct = $total > 0 ? intval(round(($done / $total) * 100)) : 0;

    return [
        'total' => $total,
        'completed' => $completed,
        'not_assessed' => $not_assessed,
        'in_progress' => $in_progress,
        'done' => $done,
        'percentage' => $pct,
        'sections' => $statuses,
    ];
}

function tbrValidateCompletion(int $tbr_id, ?string $candidate_next_review_date = null): array {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        return ['valid' => false, 'errors' => ['TBR meeting not found.'], 'warnings' => []];
    }

    $errors = [];
    $warnings = [];
    $sections = tbrGetSectionsConfig();
    $progress = tbrCalculateProgress($tbr_id);

    foreach ($sections as $key => $cfg) {
        if (!empty($cfg['required'])) {
            $st = $progress['sections'][$key]['status'] ?? 'Not Started';
            if (!in_array($st, ['Completed', 'Not Assessed'], true)) {
                $errors[] = "Section {$cfg['number']} ({$cfg['title']}) must be marked Completed or Not Assessed (currently $st).";
            }
        }
    }

    $effective_next_date = ($candidate_next_review_date !== null && trim($candidate_next_review_date) !== '')
        ? trim($candidate_next_review_date)
        : ($meeting['tbr_next_review_date'] ?? '');

    if (empty($effective_next_date) || $effective_next_date === '0000-00-00') {
        $errors[] = 'Next review date is required before completing the TBR.';
    }

    $unassigned_actions = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tbr_action_items
        WHERE action_tbr_id = $tbr_id
          AND action_status NOT IN ('Completed', 'Cancelled')
          AND action_responsible_party = 'MSP'
          AND (action_assigned_user_id IS NULL OR action_assigned_user_id = 0)
    "))[0] ?? 0);
    if ($unassigned_actions > 0) {
        $errors[] = "$unassigned_actions MSP action item(s) do not have an assigned employee.";
    }

    $unnamed_client_actions = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tbr_action_items
        WHERE action_tbr_id = $tbr_id
          AND action_status NOT IN ('Completed', 'Cancelled')
          AND action_responsible_party = 'Client'
          AND (action_client_owner IS NULL OR TRIM(action_client_owner) = '')
    "))[0] ?? 0);
    if ($unnamed_client_actions > 0) {
        $warnings[] = "$unnamed_client_actions Client action item(s) have no client owner specified.";
    }

    $undoc_findings = intval(mysqli_fetch_row(mysqli_query($mysqli, "
        SELECT COUNT(*) FROM tbr_findings
        WHERE finding_tbr_id = $tbr_id
          AND finding_status IN ('Open', 'In Progress')
          AND (finding_recommended_action IS NULL OR TRIM(finding_recommended_action) = '')
    "))[0] ?? 0);
    if ($undoc_findings > 0) {
        $warnings[] = "$undoc_findings open finding(s) do not have a recommended action documented.";
    }

    $rec_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tbr_recommendations WHERE recommendation_tbr_id = $tbr_id"))[0] ?? 0);
    if ($rec_count === 0) {
        $warnings[] = 'No strategic IT recommendations have been recorded for this TBR.';
    }

    if (empty(trim((string)$meeting['tbr_executive_summary']))) {
        $warnings[] = 'Executive Summary is blank (recommended for client PDF).';
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function tbrComputeMeetingSignature(array $tbr): string {
    $parts = [
        $tbr['tbr_meeting_date'] ?? '',
        $tbr['tbr_start_time'] ?? '',
        intval($tbr['tbr_duration_minutes'] ?? 60),
        trim((string)($tbr['tbr_location'] ?? '')),
    ];
    return hash('sha256', implode('|', $parts));
}

function tbrBuildIcsString(array $tbr, array $company, ?array $rep, string $method = 'REQUEST'): string {
    require_once __DIR__ . '/../libs/zapcal/zapcallib.php';

    $start_dt = ($tbr['tbr_meeting_date'] ?? date('Y-m-d')) . ' ' . ($tbr['tbr_start_time'] ?? '09:00:00');
    $duration = max(15, intval($tbr['tbr_duration_minutes'] ?? 60));
    $end_ts = strtotime($start_dt . " +$duration minutes");
    $end_dt = date('Y-m-d H:i:s', $end_ts ?: strtotime($start_dt));

    $host = $_SERVER['SERVER_NAME'] ?? 'itflow';
    $uid = "tbr-" . intval($tbr['tbr_id']) . "@$host";
    $seq = intval($tbr['tbr_ical_sequence'] ?? 0);
    $title = ($tbr['tbr_title'] ?? 'Technology Business Review') . (!empty($tbr['client_name']) ? ' - ' . $tbr['client_name'] : '');
    $location = (string)($tbr['tbr_location'] ?? '');
    $desc = "Technology Business Review with " . ($company['company_name'] ?? 'MSP');
    if ($rep && !empty($rep['user_name'])) {
        $desc .= " | MSP Representative: " . $rep['user_name'];
    }

    $cal = new ZCiCal();
    $cal->tree->data['METHOD'] = new ZCiCalDataNode("METHOD:" . strtoupper($method));

    $event = new ZCiCalNode("VEVENT", $cal->curnode);
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $title));
    $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($start_dt)));
    $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($end_dt)));
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    $event->addNode(new ZCiCalDataNode("SEQUENCE:" . $seq));
    if ($method === 'CANCEL') {
        $event->addNode(new ZCiCalDataNode("STATUS:CANCELLED"));
    } else {
        $event->addNode(new ZCiCalDataNode("STATUS:CONFIRMED"));
        $event->addNode(new ZCiCalDataNode("LOCATION:" . $location));
        $event->addNode(new ZCiCalDataNode("DESCRIPTION:" . $desc));
        if ($rep && !empty($rep['user_email'])) {
            $cn = str_replace([';', ':', ','], '', $rep['user_name'] ?? 'MSP');
            $event->addNode(new ZCiCalDataNode("ORGANIZER;CN=" . $cn . ":MAILTO:" . $rep['user_email']));
        }
    }

    return $cal->export();
}

function tbrRenderEmailTemplate(string $template, array $vars): string {
    $search = [];
    $replace = [];
    foreach ($vars as $k => $v) {
        $search[] = '{{' . $k . '}}';
        $replace[] = (string)$v;
    }
    return str_replace($search, $replace, $template);
}

function tbrSyncCalendarEvent(int $tbr_id): ?int {
    global $mysqli, $config_default_calendar;

    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        return null;
    }

    $event_id = intval($meeting['tbr_calendar_event_id'] ?? 0);
    if ($meeting['tbr_status'] === 'Cancelled' || !empty($meeting['tbr_archived_at'])) {
        if ($event_id > 0) {
            mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_id = $event_id");
            mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_calendar_event_id = NULL WHERE tbr_id = $tbr_id");
        }
        return null;
    }

    $cal_id = intval($config_default_calendar ?? 0);
    if ($cal_id <= 0) {
        $cal_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_archived_at IS NULL ORDER BY calendar_id ASC LIMIT 1"));
        $cal_id = intval($cal_row[0] ?? 0);
    } else {
        $chk = mysqli_fetch_row(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_id = $cal_id AND calendar_archived_at IS NULL LIMIT 1"));
        if (!$chk) {
            $cal_row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_archived_at IS NULL ORDER BY calendar_id ASC LIMIT 1"));
            $cal_id = intval($cal_row[0] ?? 0);
        }
    }

    if ($cal_id <= 0) {
        return null;
    }

    $start = $meeting['tbr_meeting_date'] . ' ' . $meeting['tbr_start_time'];
    $duration = max(15, intval($meeting['tbr_duration_minutes']));
    $end = date('Y-m-d H:i:s', strtotime($start . " +$duration minutes"));
    $title = escapeSql("TBR: " . $meeting['client_name'] . " - " . $meeting['tbr_title']);
    $loc = escapeSql((string)($meeting['tbr_location'] ?? ''));
    $desc = escapeSql("Technology Business Review ({$meeting['tbr_type']}) | Assigned: " . ($meeting['rep_name'] ?? 'Unassigned'));
    $client_id = intval($meeting['tbr_client_id']);

    if ($event_id > 0) {
        mysqli_query($mysqli, "
            UPDATE calendar_events SET
                event_title = '$title',
                event_location = '$loc',
                event_description = '$desc',
                event_start = '$start',
                event_end = '$end',
                event_client_id = $client_id
            WHERE event_id = $event_id
        ");
        return $event_id;
    }

    mysqli_query($mysqli, "
        INSERT INTO calendar_events SET
            event_title = '$title',
            event_location = '$loc',
            event_description = '$desc',
            event_start = '$start',
            event_end = '$end',
            event_all_day = 0,
            event_client_id = $client_id,
            event_calendar_id = $cal_id
    ");
    $new_event_id = mysqli_insert_id($mysqli);
    if ($new_event_id > 0) {
        mysqli_query($mysqli, "UPDATE tbr_meetings SET tbr_calendar_event_id = $new_event_id WHERE tbr_id = $tbr_id");
        return $new_event_id;
    }

    return null;
}

function tbrBuildMeetingDetailsCardHtml(array $vars, string $accent = '#dc2626'): string {
    $loc_html = $vars['location_html'] ?? ($vars['location'] ?? 'TBD');
    return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid ' . $accent . '; border-radius:6px; margin:12px 0;">'
        . '<tr><td style="padding:14px 18px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px; line-height:1.5; color:#1e293b;">'
        . '<tr><td width="150" valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">Client</td><td valign="top" style="padding:5px 0; font-weight:600; color:#0f172a;">' . ($vars['client_name'] ?? '') . '</td></tr>'
        . '<tr><td valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">Review</td><td valign="top" style="padding:5px 0; color:#0f172a;">' . ($vars['tbr_title'] ?? '') . '</td></tr>'
        . '<tr><td valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">Date</td><td valign="top" style="padding:5px 0; color:#0f172a;">' . ($vars['meeting_date'] ?? '') . '</td></tr>'
        . '<tr><td valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">Time</td><td valign="top" style="padding:5px 0; color:#0f172a;">' . ($vars['meeting_time'] ?? '') . ' (' . ($vars['duration'] ?? '60') . ' minutes)</td></tr>'
        . '<tr><td valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">Location / Link</td><td valign="top" style="padding:5px 0; color:#0f172a;">' . $loc_html . '</td></tr>'
        . '<tr><td valign="top" style="padding:5px 10px 5px 0; color:#64748b; font-weight:600;">MSP Representative</td><td valign="top" style="padding:5px 0; color:#0f172a;">' . ($vars['rep_name'] ?? '') . ' (<a href="mailto:' . ($vars['rep_email'] ?? '') . '" style="color:#2563eb; text-decoration:none;">' . ($vars['rep_email'] ?? '') . '</a>)</td></tr>'
        . '</table>'
        . '</td></tr></table>';
}

function tbrWrapEmailHtml(string $inner_html, array $meta = []): string {
    global $config_base_url, $config_theme, $config_https_only;

    $theme_map = [
        'red' => '#dc2626',
        'blue' => '#2563eb',
        'indigo' => '#4f46e5',
        'green' => '#16a34a',
        'teal' => '#0d9488',
        'purple' => '#7c3aed',
        'orange' => '#ea580c',
        'dark' => '#334155',
    ];
    $accent = $theme_map[strtolower((string)($config_theme ?? 'red'))] ?? '#dc2626';
    $msp_name = escapeHtml($meta['msp_name'] ?? 'Managed IT Services');
    $badge_label = escapeHtml($meta['badge'] ?? 'Technology Business Review');
    $badge_bg = $meta['badge_bg'] ?? '#1e293b';
    $company_email = escapeHtml($meta['company_email'] ?? '');
    $company_website = trim((string)($meta['company_website'] ?? ''));
    $company_phone = escapeHtml($meta['company_phone'] ?? '');
    $logo_file = basename((string)($meta['company_logo'] ?? ''));

    $logo_html = '';
    if ($logo_file !== '' && !empty($config_base_url) && file_exists(__DIR__ . '/../uploads/settings/' . $logo_file)) {
        $scheme = !empty($config_https_only) ? 'https' : 'http';
        $logo_url = $scheme . '://' . $config_base_url . '/uploads/settings/' . rawurlencode($logo_file);
        $logo_html = '<img src="' . escapeHtml($logo_url) . '" alt="' . $msp_name . '" style="max-height:38px; width:auto; display:block; border:0;">';
    } else {
        $logo_html = '<span style="font-size:18px; font-weight:700; color:#ffffff; letter-spacing:0.2px;">' . $msp_name . '</span>';
    }

    $ics_notice = '';
    if (!empty($meta['has_ics'])) {
        $ics_notice = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; margin-top:20px;">'
            . '<tr><td style="padding:12px 16px; font-size:13px; color:#1e40af;">'
            . '<strong>Calendar Invitation Attached (TBR_Meeting.ics)</strong> &mdash; Open the attached calendar file to add or update this event in Outlook, Google Calendar, or Apple Calendar.'
            . '</td></tr></table>';
    }

    $footer_links = [];
    if ($company_email !== '') {
        $footer_links[] = '<a href="mailto:' . $company_email . '" style="color:#475569; text-decoration:none;">' . $company_email . '</a>';
    }
    if ($company_phone !== '') {
        $footer_links[] = $company_phone;
    }
    if ($company_website !== '') {
        $href = preg_match('#^https?://#i', $company_website) ? $company_website : ('https://' . $company_website);
        $footer_links[] = '<a href="' . escapeHtml($href) . '" style="color:#475569; text-decoration:none;">' . escapeHtml($company_website) . '</a>';
    }
    $footer_contact = !empty($footer_links) ? '<div style="margin-top:4px;">' . implode(' &nbsp;&middot;&nbsp; ', $footer_links) . '</div>' : '';

    return '<!DOCTYPE html>'
        . '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
        . '<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#1e293b;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f1f5f9; padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="620" style="max-width:620px; width:100%; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">'
        . '<tr><td style="background-color:#0f172a; padding:20px 28px; border-bottom:4px solid ' . $accent . ';">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>'
        . '<td valign="middle" align="left">' . $logo_html . '</td>'
        . '<td valign="middle" align="right"><span style="display:inline-block; background-color:' . $badge_bg . '; color:#e2e8f0; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; padding:5px 10px; border-radius:4px; border:1px solid #334155;">' . $badge_label . '</span></td>'
        . '</tr></table>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px; font-size:15px; line-height:1.6; color:#1e293b;">'
        . $inner_html
        . $ics_notice
        . '</td></tr>'
        . '<tr><td style="background-color:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 32px; font-size:12px; line-height:1.5; color:#64748b; text-align:center;">'
        . '<strong style="color:#334155;">' . $msp_name . '</strong>'
        . $footer_contact
        . '<div style="margin-top:6px; color:#94a3b8;">Sent via ' . $msp_name . ' Technology Business Review Portal</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}

function tbrQueueMeetingEmail(int $tbr_id, string $type, string $audience, ?string $custom_subject = null, ?string $custom_body = null, ?array $attachments = null, ?array $override_recipients = null): int {
    global $mysqli, $config_mail_from_email, $config_mail_from_name, $config_theme;

    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        return 0;
    }

    $settings = tbrGetSettings();
    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1 LIMIT 1")) ?: [];
    $from_email = !empty($config_mail_from_email) ? $config_mail_from_email : ($company['company_email'] ?? '');
    $from_name = !empty($config_mail_from_name) ? $config_mail_from_name : ($company['company_name'] ?? 'MSP');

    if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }

    $rep = null;
    if (!empty($meeting['tbr_assigned_user_id'])) {
        $rep = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_name, user_email FROM users WHERE user_id = " . intval($meeting['tbr_assigned_user_id']) . " LIMIT 1"));
    }

    $recipients = [];
    if ($override_recipients !== null) {
        $recipients = $override_recipients;
    } elseif ($audience === 'client') {
        $res_att = mysqli_query($mysqli, "
            SELECT attendee_name, attendee_email
            FROM tbr_attendees
            WHERE attendee_tbr_id = $tbr_id
              AND attendee_type IN ('client', 'external')
              AND attendee_receive_notifications = 1
              AND attendee_email IS NOT NULL AND attendee_email != ''
        ");
        while ($res_att && ($r = mysqli_fetch_assoc($res_att))) {
            $email = trim($r['attendee_email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = $r['attendee_name'] ?: $email;
            }
        }
    } elseif ($audience === 'internal') {
        if ($rep && filter_var($rep['user_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $recipients[$rep['user_email']] = $rep['user_name'] ?: $rep['user_email'];
        }
        $res_att = mysqli_query($mysqli, "
            SELECT attendee_name, attendee_email
            FROM tbr_attendees
            WHERE attendee_tbr_id = $tbr_id
              AND attendee_type = 'internal'
              AND attendee_receive_notifications = 1
              AND attendee_email IS NOT NULL AND attendee_email != ''
        ");
        while ($res_att && ($r = mysqli_fetch_assoc($res_att))) {
            $email = trim($r['attendee_email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = $r['attendee_name'] ?: $email;
            }
        }
    }

    if (empty($recipients)) {
        return 0;
    }

    $badge_map = [
        'scheduled_confirmation' => 'Meeting Scheduled',
        'rescheduled' => 'Meeting Rescheduled',
        'cancelled' => 'Meeting Cancelled',
        'report_delivery' => 'Executive TBR Report',
        'reminder_7d' => 'Upcoming Review Reminder',
        'reminder_1d' => 'Tomorrow: Business Review',
        'reminder_day_of' => 'Today: Business Review',
        'overdue_alert' => 'Overdue TBR Alert',
        'action_due_alert' => 'Action Item Due Alert',
    ];
    $badge_label = $badge_map[$type] ?? 'Technology Business Review';
    if ($audience === 'internal' && !in_array($type, ['overdue_alert', 'action_due_alert', 'cancelled'], true)) {
        $badge_label = 'Internal MSP Notice';
    }

    if ($custom_subject !== null && $custom_body !== null) {
        $tpl_subject = $custom_subject;
        $tpl_body = $custom_body;
    } elseif ($audience === 'internal') {
        $tpl_subject = $settings['tpl_internal_reminder_subject'];
        $tpl_body = $settings['tpl_internal_reminder_body'];
    } elseif ($type === 'scheduled_confirmation' || $type === 'rescheduled') {
        $tpl_subject = ($type === 'rescheduled' ? '[Rescheduled] ' : '') . $settings['tpl_confirmation_subject'];
        $tpl_body = $settings['tpl_confirmation_body'];
    } elseif ($type === 'cancelled') {
        $tpl_subject = 'Cancelled: Technology Business Review - {{tbr_title}} ({{meeting_date}})';
        $tpl_body = '<p style="margin:0 0 14px 0;">Hello {{attendee_name}},</p>'
            . '<p style="margin:0 0 18px 0;">Please note that the following Technology Business Review has been <strong>cancelled</strong>:</p>'
            . '{{meeting_details_card}}'
            . '<p style="margin:18px 0 0 0;">Please contact <strong>{{rep_name}}</strong> at <a href="mailto:{{rep_email}}" style="color:#2563eb; text-decoration:none;">{{rep_email}}</a> if you would like to schedule a new date.</p>';
    } elseif ($type === 'report_delivery') {
        $tpl_subject = $settings['tpl_report_subject'];
        $tpl_body = $settings['tpl_report_body'];
    } else {
        $tpl_subject = $settings['tpl_reminder_subject'];
        $tpl_body = $settings['tpl_reminder_body'];
    }

    $cal_str = null;
    if (!empty($settings['attach_ics_calendar']) && in_array($type, ['scheduled_confirmation', 'rescheduled', 'cancelled'], true)) {
        $ics_method = ($type === 'cancelled') ? 'CANCEL' : 'REQUEST';
        $cal_str = tbrBuildIcsString($meeting, $company, $rep, $ics_method);
    }

    $period_label = trim(($meeting['tbr_reporting_period_start'] ?: '') . ' to ' . ($meeting['tbr_reporting_period_end'] ?: ''));
    if ($period_label === 'to') {
        $period_label = 'Current Review Period';
    }

    $raw_location = trim((string)($meeting['tbr_location'] ?? ''));
    if ($raw_location === '') {
        $location_display = 'TBD';
        $location_html = '<span style="color:#64748b;">To Be Determined</span>';
    } elseif (preg_match('#^https?://#i', $raw_location)) {
        $location_display = escapeHtml($raw_location);
        $location_html = '<a href="' . escapeUrl($raw_location) . '" style="color:#2563eb; font-weight:600; text-decoration:underline;">' . escapeHtml($raw_location) . '</a>';
    } else {
        $location_display = escapeHtml($raw_location);
        $location_html = escapeHtml($raw_location);
    }

    $theme_map = [
        'red' => '#dc2626',
        'blue' => '#2563eb',
        'indigo' => '#4f46e5',
        'green' => '#16a34a',
        'teal' => '#0d9488',
        'purple' => '#7c3aed',
        'orange' => '#ea580c',
        'dark' => '#334155',
    ];
    $accent = $theme_map[strtolower((string)($config_theme ?? 'red'))] ?? '#dc2626';

    $vars = [
        'client_name' => escapeHtml($meeting['client_name'] ?? ''),
        'tbr_title' => escapeHtml($meeting['tbr_title'] ?? ''),
        'meeting_date' => date('F j, Y', strtotime($meeting['tbr_meeting_date'])),
        'meeting_time' => date('g:i A', strtotime($meeting['tbr_start_time'])),
        'duration' => intval($meeting['tbr_duration_minutes']),
        'location' => $location_display,
        'location_html' => $location_html,
        'msp_name' => escapeHtml($company['company_name'] ?? 'MSP'),
        'rep_name' => escapeHtml($rep['user_name'] ?? 'Your Account Team'),
        'rep_email' => escapeHtml($rep['user_email'] ?? $from_email),
        'reporting_period' => escapeHtml($period_label),
        'next_review_date' => !empty($meeting['tbr_next_review_date']) ? date('F j, Y', strtotime($meeting['tbr_next_review_date'])) : 'TBD',
    ];
    $vars['meeting_details_card'] = tbrBuildMeetingDetailsCardHtml($vars, $accent);

    if (strip_tags($tpl_body) === $tpl_body) {
        $tpl_body = '<p style="margin:0 0 14px 0;">' . nl2br($tpl_body) . '</p>';
    }

    $wrap_meta = [
        'msp_name' => $company['company_name'] ?? 'MSP',
        'company_logo' => $company['company_logo'] ?? '',
        'company_email' => $company['company_email'] ?? $from_email,
        'company_phone' => $company['company_phone'] ?? '',
        'company_website' => $company['company_website'] ?? '',
        'badge' => $badge_label,
        'badge_bg' => ($type === 'cancelled' || $type === 'overdue_alert') ? '#7f1d1d' : '#1e293b',
        'has_ics' => ($cal_str !== null && $type !== 'cancelled'),
    ];

    $batch = [];
    foreach ($recipients as $email => $name) {
        $vars['attendee_name'] = escapeHtml($name);
        $rendered_subject = tbrRenderEmailTemplate($tpl_subject, $vars);
        $rendered_body = tbrRenderEmailTemplate($tpl_body, $vars);
        if (stripos($rendered_body, '<!DOCTYPE') === false && stripos($rendered_body, '<html') === false) {
            $rendered_body = tbrWrapEmailHtml($rendered_body, $wrap_meta);
        }

        $email_item = [
            'from' => mysqli_real_escape_string($mysqli, $from_email),
            'from_name' => mysqli_real_escape_string($mysqli, $from_name),
            'recipient' => mysqli_real_escape_string($mysqli, $email),
            'recipient_name' => mysqli_real_escape_string($mysqli, $name),
            'subject' => mysqli_real_escape_string($mysqli, $rendered_subject),
            'body' => mysqli_real_escape_string($mysqli, $rendered_body),
        ];
        if ($cal_str !== null) {
            $email_item['cal_str'] = $cal_str;
        }
        if (!empty($attachments)) {
            $email_item['attachments'] = $attachments;
        }
        $batch[] = $email_item;
    }

    if (!empty($batch)) {
        addToMailQueue($batch);
        return count($batch);
    }

    return 0;
}

function tbrSyncNotificationSchedules(int $tbr_id, bool $send_immediate = false, string $immediate_type = 'scheduled_confirmation'): void {
    global $mysqli;

    $tbr_id = intval($tbr_id);
    $meeting = tbrGetMeeting($tbr_id);
    if (!$meeting) {
        return;
    }

    mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Cancelled' WHERE schedule_tbr_id = $tbr_id AND schedule_status = 'Pending'");

    $settings = tbrGetSettings();
    $sig = escapeSql(tbrComputeMeetingSignature($meeting));
    $meeting_dt = $meeting['tbr_meeting_date'] . ' ' . $meeting['tbr_start_time'];
    $meeting_ts = strtotime($meeting_dt);

    if ($meeting['tbr_status'] === 'Cancelled') {
        if ($send_immediate && !empty($settings['enable_confirmation_email'])) {
            if (!empty($settings['send_client_confirmation'])) {
                tbrQueueMeetingEmail($tbr_id, 'cancelled', 'client');
            }
            if (!empty($settings['send_internal_confirmation'])) {
                tbrQueueMeetingEmail($tbr_id, 'cancelled', 'internal');
            }
        }
        return;
    }

    if ($send_immediate && !empty($settings['enable_confirmation_email'])) {
        if (!empty($settings['send_client_confirmation'])) {
            $sent_c = tbrQueueMeetingEmail($tbr_id, $immediate_type, 'client');
            if ($sent_c > 0) {
                $t_esc = escapeSql($immediate_type);
                mysqli_query($mysqli, "INSERT INTO tbr_notification_schedules SET schedule_tbr_id = $tbr_id, schedule_type = '$t_esc', schedule_audience = 'client', schedule_send_at = NOW(), schedule_status = 'Sent', schedule_sent_at = NOW(), schedule_meeting_signature = '$sig'");
            }
        }
        if (!empty($settings['send_internal_confirmation'])) {
            $sent_i = tbrQueueMeetingEmail($tbr_id, $immediate_type, 'internal');
            if ($sent_i > 0) {
                $t_esc = escapeSql($immediate_type);
                mysqli_query($mysqli, "INSERT INTO tbr_notification_schedules SET schedule_tbr_id = $tbr_id, schedule_type = '$t_esc', schedule_audience = 'internal', schedule_send_at = NOW(), schedule_status = 'Sent', schedule_sent_at = NOW(), schedule_meeting_signature = '$sig'");
            }
        }
    }

    if (!in_array($meeting['tbr_status'], ['Scheduled', 'In Progress'], true) || $meeting_ts === false) {
        return;
    }

    $rem1_days = max(1, intval($settings['reminder_1_days'] ?? 7));
    $rem2_days = max(1, intval($settings['reminder_2_days'] ?? 1));
    $day_of_hours = max(1, intval($settings['day_of_hours_before'] ?? 3));

    $schedules = [];
    if (!empty($settings['enable_7d_reminder'])) {
        $ts = strtotime("-$rem1_days days", $meeting_ts);
        if ($ts > time()) {
            if (!empty($settings['send_client_7d'])) {
                $schedules[] = ['reminder_7d', 'client', date('Y-m-d H:i:s', $ts)];
            }
            if (!empty($settings['enable_internal_reminders']) && !empty($settings['send_internal_7d'])) {
                $schedules[] = ['reminder_7d', 'internal', date('Y-m-d H:i:s', $ts)];
            }
        }
    }

    if (!empty($settings['enable_1d_reminder'])) {
        $ts = strtotime("-$rem2_days days", $meeting_ts);
        if ($ts > time()) {
            if (!empty($settings['send_client_1d'])) {
                $schedules[] = ['reminder_1d', 'client', date('Y-m-d H:i:s', $ts)];
            }
            if (!empty($settings['enable_internal_reminders']) && !empty($settings['send_internal_1d'])) {
                $schedules[] = ['reminder_1d', 'internal', date('Y-m-d H:i:s', $ts)];
            }
        }
    }

    if (!empty($settings['enable_day_of_reminder'])) {
        $ts = strtotime("-$day_of_hours hours", $meeting_ts);
        if ($ts > time()) {
            if (!empty($settings['send_client_day_of'])) {
                $schedules[] = ['reminder_day_of', 'client', date('Y-m-d H:i:s', $ts)];
            }
            if (!empty($settings['enable_internal_reminders']) && !empty($settings['send_internal_day_of'])) {
                $schedules[] = ['reminder_day_of', 'internal', date('Y-m-d H:i:s', $ts)];
            }
        }
    }

    foreach ($schedules as [$stype, $saud, $send_at]) {
        mysqli_query($mysqli, "
            INSERT INTO tbr_notification_schedules SET
                schedule_tbr_id = $tbr_id,
                schedule_type = '$stype',
                schedule_audience = '$saud',
                schedule_send_at = '$send_at',
                schedule_status = 'Pending',
                schedule_meeting_signature = '$sig'
        ");
    }
}

function tbrCreateTicketForActionItem(int $action_id, int $user_id): array {
    global $mysqli, $config_ticket_prefix;

    $action_id = intval($action_id);
    $user_id = intval($user_id);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT a.*, m.tbr_title, m.tbr_meeting_date
        FROM tbr_action_items a
        JOIN tbr_meetings m ON a.action_tbr_id = m.tbr_id
        WHERE a.action_id = $action_id LIMIT 1
    "));

    if (!$row) {
        return ['ok' => false, 'error' => 'Action item not found'];
    }

    if (!empty($row['action_ticket_id'])) {
        return ['ok' => true, 'ticket_id' => intval($row['action_ticket_id']), 'already_linked' => true];
    }

    $client_id = intval($row['action_client_id']);
    $assigned_to = intval($row['action_assigned_user_id'] ?? 0);
    $ticket_status = $assigned_to > 0 ? 2 : 1;

    $prio_map = ['Critical' => 'High', 'High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low'];
    $priority = escapeSql($prio_map[$row['action_priority']] ?? 'Medium');

    $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL LIMIT 1"));
    $contact_id = intval($contact_row['contact_id'] ?? 0);

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");
    $ticket_number = mysqli_insert_id($mysqli);
    $prefix_esc = escapeSql($config_ticket_prefix ?? 'TCK-');
    $url_key = randomString(32);

    $short_desc = truncate(trim((string)$row['action_description']), 120);
    $subject = escapeSql("TBR Action: " . $short_desc);
    $details_raw = "<p><strong>Created from Technology Business Review:</strong> " . escapeHtml($row['tbr_title']) . " (" . escapeHtml($row['tbr_meeting_date']) . ")</p>"
        . "<p><strong>Action Item:</strong><br>" . nl2br(escapeHtml($row['action_description'])) . "</p>"
        . "<p><strong>Responsible Party:</strong> " . escapeHtml($row['action_responsible_party']) . "</p>";
    $details = mysqli_real_escape_string($mysqli, $details_raw);
    $due_sql = !empty($row['action_due_date']) ? "'" . escapeSql($row['action_due_date']) . " 17:00:00'" : 'NULL';

    mysqli_query($mysqli, "
        INSERT INTO tickets SET
            ticket_prefix = '$prefix_esc',
            ticket_number = $ticket_number,
            ticket_source = 'Agent',
            ticket_category = 0,
            ticket_subject = '$subject',
            ticket_details = '$details',
            ticket_priority = '$priority',
            ticket_billable = 0,
            ticket_status = $ticket_status,
            ticket_created_by = $user_id,
            ticket_assigned_to = $assigned_to,
            ticket_contact_id = $contact_id,
            ticket_url_key = '$url_key',
            ticket_due_at = $due_sql,
            ticket_client_id = $client_id
    ");
    $ticket_id = mysqli_insert_id($mysqli);
    if ($ticket_id <= 0) {
        return ['ok' => false, 'error' => 'Failed to create ticket'];
    }

    if (function_exists('applyTicketSla')) {
        applyTicketSla($ticket_id);
    }

    $task_name = escapeSql(truncate($row['action_description'], 240));
    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_order = 1, task_ticket_id = $ticket_id");
    $task_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "UPDATE tbr_action_items SET action_ticket_id = $ticket_id, action_task_id = $task_id WHERE action_id = $action_id");
    tbrLogAudit(intval($row['action_tbr_id']), $client_id, 'Action Ticket Created', "Created Ticket {$config_ticket_prefix}{$ticket_number} (#$ticket_id) for action item #$action_id", $user_id);

    return [
        'ok' => true,
        'ticket_id' => $ticket_id,
        'task_id' => $task_id,
        'ticket_ref' => ($config_ticket_prefix ?? 'TCK-') . $ticket_number,
    ];
}

function tbrLogAudit(int $tbr_id, int $client_id, string $action, string $details, ?int $user_id = null): void {
    global $mysqli, $session_user_id, $session_ip;

    $tbr_id = intval($tbr_id);
    $client_id = intval($client_id);
    $uid = $user_id !== null ? intval($user_id) : intval($session_user_id ?? 0);
    $ip = escapeSql($session_ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $action_esc = escapeSql(substr($action, 0, 100));
    $details_esc = escapeSql(substr($details, 0, 1000));

    mysqli_query($mysqli, "
        INSERT INTO tbr_audit_history SET
            audit_tbr_id = $tbr_id,
            audit_client_id = $client_id,
            audit_action = '$action_esc',
            audit_details = '$details_esc',
            audit_user_id = $uid,
            audit_ip = '$ip'
    ");

    if (function_exists('logAudit') && php_sapi_name() !== 'cli') {
        logAudit('TBR', $action_esc, $details_esc, $client_id, $tbr_id);
    }
}
