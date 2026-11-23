<?php

chdir(dirname(__FILE__));

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$cli_opts = !defined('ITFLOW_CRON_DISPATCHER') ? getopt('', ['force', 'dry-run']) : [];
$is_force = isset($cli_opts['force']);
$is_dry_run = isset($cli_opts['dry-run']);

$settings_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));
$config_enable_cron = intval($settings_row['config_enable_cron'] ?? 0);

if ($config_enable_cron === 0 && !$is_force && !$is_dry_run) {
    cronJobStop("Cron is not enabled");
}

$tbr_settings = tbrGetSettings();
$sent_count = 0;
$skipped_count = 0;
$overdue_count = 0;
$action_due_count = 0;

$sql_due = mysqli_query($mysqli, "
    SELECT s.*, m.tbr_status, m.tbr_archived_at, m.tbr_meeting_date, m.tbr_start_time, m.tbr_duration_minutes, m.tbr_location, m.tbr_title, m.tbr_client_id
    FROM tbr_notification_schedules s
    JOIN tbr_meetings m ON s.schedule_tbr_id = m.tbr_id
    WHERE s.schedule_status = 'Pending'
      AND s.schedule_send_at <= NOW()
    ORDER BY s.schedule_send_at ASC
");

while ($sql_due && ($row = mysqli_fetch_assoc($sql_due))) {
    $sid = intval($row['schedule_id']);
    $tid = intval($row['schedule_tbr_id']);
    $sig = tbrComputeMeetingSignature($row);

    if (!empty($row['tbr_archived_at']) || !in_array($row['tbr_status'], ['Scheduled', 'In Progress'], true) || $sig !== $row['schedule_meeting_signature']) {
        if (!$is_dry_run) {
            mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Skipped' WHERE schedule_id = $sid");
        }
        $skipped_count++;
        continue;
    }

    if ($is_dry_run) {
        $sent_count++;
        continue;
    }

    $queued = tbrQueueMeetingEmail($tid, $row['schedule_type'], $row['schedule_audience']);
    if ($queued > 0) {
        mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Sent', schedule_sent_at = NOW() WHERE schedule_id = $sid");
        tbrLogAudit($tid, intval($row['tbr_client_id']), 'Reminder Queued', "Queued {$row['schedule_type']} ({$row['schedule_audience']}) to $queued recipient(s)", 0);
        $sent_count++;
    } else {
        mysqli_query($mysqli, "UPDATE tbr_notification_schedules SET schedule_status = 'Skipped', schedule_error = 'No eligible recipients or sender not configured' WHERE schedule_id = $sid");
        $skipped_count++;
    }
}

if (!empty($tbr_settings['enable_internal_reminders']) && !empty($tbr_settings['enable_overdue_reminders'])) {
    $sql_overdue = mysqli_query($mysqli, "
        SELECT m.*, c.client_name
        FROM tbr_meetings m
        JOIN clients c ON m.tbr_client_id = c.client_id
        WHERE m.tbr_archived_at IS NULL
          AND m.tbr_status = 'Scheduled'
          AND m.tbr_meeting_date < CURDATE()
          AND NOT EXISTS (
              SELECT 1 FROM tbr_notification_schedules s
              WHERE s.schedule_tbr_id = m.tbr_id
                AND s.schedule_type = 'overdue_alert'
                AND s.schedule_status = 'Sent'
                AND s.schedule_sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          )
    ");

    while ($sql_overdue && ($ov = mysqli_fetch_assoc($sql_overdue))) {
        $tid = intval($ov['tbr_id']);
        $cid = intval($ov['tbr_client_id']);
        if ($is_dry_run) {
            $overdue_count++;
            continue;
        }
        $subj = "[Overdue TBR] {$ov['client_name']} — {$ov['tbr_title']} ({$ov['tbr_meeting_date']})";
        $body = "<p>Hello {{attendee_name}},</p><p>The scheduled Technology Business Review <strong>{{tbr_title}}</strong> for <strong>{{client_name}}</strong> on <strong>{{meeting_date}}</strong> is now overdue. Please conduct or reschedule the meeting in ITFlow.</p>";
        $queued = tbrQueueMeetingEmail($tid, 'overdue_alert', 'internal', $subj, $body);
        $sig = escapeSql(tbrComputeMeetingSignature($ov));
        mysqli_query($mysqli, "INSERT INTO tbr_notification_schedules SET schedule_tbr_id = $tid, schedule_type = 'overdue_alert', schedule_audience = 'internal', schedule_send_at = NOW(), schedule_status = '" . ($queued > 0 ? 'Sent' : 'Skipped') . "', schedule_sent_at = NOW(), schedule_meeting_signature = '$sig'");
        if (function_exists('appNotify')) {
            appNotify('TBR Overdue', escapeSql("Overdue TBR for {$ov['client_name']}: {$ov['tbr_title']} ({$ov['tbr_meeting_date']})"), "/agent/tbr.php?tbr_id=$tid", $cid, $tid);
        }
        $overdue_count++;
    }
}

if (!empty($tbr_settings['enable_internal_reminders']) && !empty($tbr_settings['enable_action_due_reminders'])) {
    $days_before = max(1, intval($tbr_settings['action_due_days_before'] ?? 3));
    $sql_act = mysqli_query($mysqli, "
        SELECT a.*, m.tbr_title, m.tbr_meeting_date, m.tbr_start_time, m.tbr_duration_minutes, m.tbr_location,
               c.client_name, u.user_name, u.user_email
        FROM tbr_action_items a
        JOIN tbr_meetings m ON a.action_tbr_id = m.tbr_id
        JOIN clients c ON a.action_client_id = c.client_id
        LEFT JOIN users u ON a.action_assigned_user_id = u.user_id
        WHERE m.tbr_archived_at IS NULL
          AND a.action_status IN ('Not Started', 'In Progress', 'Waiting on Client')
          AND a.action_due_date IS NOT NULL
          AND a.action_due_date <= DATE_ADD(CURDATE(), INTERVAL $days_before DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbr_notification_schedules s
              WHERE s.schedule_action_item_id = a.action_id
                AND s.schedule_type = 'action_due_alert'
                AND s.schedule_status = 'Sent'
          )
    ");

    while ($sql_act && ($act = mysqli_fetch_assoc($sql_act))) {
        $aid = intval($act['action_id']);
        $tid = intval($act['action_tbr_id']);
        if ($is_dry_run) {
            $action_due_count++;
            continue;
        }
        $override = null;
        if (!empty($act['user_email']) && filter_var($act['user_email'], FILTER_VALIDATE_EMAIL)) {
            $override = [$act['user_email'] => ($act['user_name'] ?: $act['user_email'])];
        }
        $subj = "[TBR Action Due] {$act['client_name']} — Due {$act['action_due_date']}";
        $body = "<p>Hello {{attendee_name}},</p><p>A TBR action item for <strong>{{client_name}}</strong> is due on <strong>" . escapeHtml($act['action_due_date']) . "</strong>:</p><p>" . nl2br(escapeHtml($act['action_description'])) . "</p><p>Status: " . escapeHtml($act['action_status']) . " | Priority: " . escapeHtml($act['action_priority']) . "</p>";
        $queued = tbrQueueMeetingEmail($tid, 'action_due_alert', 'internal', $subj, $body, null, $override);
        $sig = escapeSql(tbrComputeMeetingSignature($act));
        mysqli_query($mysqli, "INSERT INTO tbr_notification_schedules SET schedule_tbr_id = $tid, schedule_type = 'action_due_alert', schedule_audience = 'internal', schedule_action_item_id = $aid, schedule_send_at = NOW(), schedule_status = '" . ($queued > 0 ? 'Sent' : 'Skipped') . "', schedule_sent_at = NOW(), schedule_meeting_signature = '$sig'");
        $action_due_count++;
    }
}

$summary = "TBR Reminders: queued=$sent_count, skipped=$skipped_count, overdue_alerts=$overdue_count, action_due_alerts=$action_due_count";
echo $summary . "\n";
if (!$is_dry_run && ($sent_count > 0 || $overdue_count > 0 || $action_due_count > 0)) {
    logApp("Cron-TBR-Reminders", "info", $summary);
}
