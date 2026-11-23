<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_smsgate_settings'])) {

    validateCSRFToken();

    $config_smsgate_enable = intval($_POST['config_smsgate_enable'] ?? 0);
    $config_smsgate_url = escapeSql(smsgateNormalizeUrl($_POST['config_smsgate_url'] ?? ''));
    $config_smsgate_username = escapeSql(trim($_POST['config_smsgate_username'] ?? ''));
    $config_smsgate_password = escapeSql($_POST['config_smsgate_password'] ?? '');
    $config_smsgate_emergency_recipients = escapeSql(trim($_POST['config_smsgate_emergency_recipients'] ?? ''));
    $config_smsgate_urgent_message = escapeSql(trim($_POST['config_smsgate_urgent_message'] ?? ''));
    $config_smsgate_ack_message = escapeSql(trim($_POST['config_smsgate_ack_message'] ?? ''));

    mysqli_query($mysqli, "UPDATE settings SET
        config_smsgate_enable = $config_smsgate_enable,
        config_smsgate_url = '$config_smsgate_url',
        config_smsgate_username = '$config_smsgate_username',
        config_smsgate_password = '$config_smsgate_password',
        config_smsgate_emergency_recipients = '$config_smsgate_emergency_recipients',
        config_smsgate_urgent_message = '$config_smsgate_urgent_message',
        config_smsgate_ack_message = '$config_smsgate_ack_message'
        WHERE company_id = 1
    ");

    logAudit("Settings", "Edit", "$session_name edited SMS Gate settings");

    flashAlert("SMS Gate Settings updated");

    redirect();

}

if (isset($_POST['test_smsgate'])) {

    validateCSRFToken();

    $test_number = trim($_POST['test_number'] ?? '');
    $test_message = trim($_POST['test_message'] ?? 'Test message from ITFlow SMSgate');

    if (empty($test_number)) {
        flashAlert("Please provide a phone number to test.", 'danger');
        redirect();
    }

    $result = smsgateSend($test_number, $test_message, true);

    if (!empty($result['success'])) {
        flashAlert("Test SMS sent successfully to $test_number!");
    } else {
        $error_msg = !empty($result['error']) ? $result['error'] : 'Unknown error';
        flashAlert("Test SMS failed: " . escapeHtml($error_msg), 'danger');
    }

    redirect();

}

if (isset($_POST['edit_tech_weekly_summary_settings']) || isset($_POST['send_tech_weekly_summary_now'])) {

    validateCSRFToken();

    smsgateEnsureTechWeeklyTable($mysqli);

    $config_tech_weekly_summary_enable = intval($_POST['config_tech_weekly_summary_enable'] ?? 0);
    $raw_time = trim($_POST['config_tech_weekly_summary_time'] ?? '08:00');
    if (!preg_match('/^\d{2}:\d{2}$/', $raw_time)) {
        $raw_time = '08:00';
    }
    $config_tech_weekly_summary_time = escapeSql($raw_time);

    mysqli_query($mysqli, "UPDATE settings SET
        config_tech_weekly_summary_enable = $config_tech_weekly_summary_enable,
        config_tech_weekly_summary_time = '$config_tech_weekly_summary_time'
        WHERE company_id = 1
    ");

    mysqli_query($mysqli, "UPDATE cron_jobs SET
        cron_job_daily_at = '{$config_tech_weekly_summary_time}:00'
        WHERE cron_job_name = 'tech_weekly_summary'
    ");

    $tech_ids = $_POST['tech_ids'] ?? [];
    $tech_enabled = $_POST['tech_enabled'] ?? [];
    $tech_sms_numbers = $_POST['tech_sms_numbers'] ?? [];
    $tech_email_enabled = $_POST['tech_email_enabled'] ?? [];
    $tech_extra_emails = $_POST['tech_extra_emails'] ?? [];

    if (is_array($tech_ids)) {
        foreach ($tech_ids as $uid_raw) {
            $u_id = intval($uid_raw);
            if ($u_id <= 0) {
                continue;
            }
            $en = !empty($tech_enabled[$u_id]) ? 1 : 0;
            $sms_nums = escapeSql(trim((string) ($tech_sms_numbers[$u_id] ?? '')));
            $em_en = !empty($tech_email_enabled[$u_id]) ? 1 : 0;
            $ex_em = escapeSql(trim((string) ($tech_extra_emails[$u_id] ?? '')));

            mysqli_query($mysqli, "
                INSERT INTO tech_weekly_summary_configs (user_id, enabled, sms_numbers, email_enabled, extra_emails)
                VALUES ($u_id, $en, '$sms_nums', $em_en, '$ex_em')
                ON DUPLICATE KEY UPDATE
                    enabled = $en,
                    sms_numbers = '$sms_nums',
                    email_enabled = $em_en,
                    extra_emails = '$ex_em'
            ");
        }
    }

    logAudit("Settings", "Edit", "$session_name edited Weekly Technician Summary settings");

    if (isset($_POST['send_tech_weekly_summary_now'])) {
        $dispatch_res = smsgateDispatchTechWeeklySummaries($mysqli, ['force' => true]);
        $summary_msg = "Weekly Summary (" . $dispatch_res['week_start'] . " to " . $dispatch_res['week_end'] . "): "
            . "Sent SMS for " . $dispatch_res['sent_sms_techs'] . " tech(s) (" . $dispatch_res['sent_sms_numbers'] . " number(s)), "
            . "queued " . $dispatch_res['queued_emails'] . " email(s), "
            . "skipped " . $dispatch_res['skipped_zero'] . " tech(s) with 0 hrs/miles.";

        if (!empty($dispatch_res['errors'])) {
            flashAlert(escapeHtml($summary_msg . " Errors: " . implode('; ', $dispatch_res['errors'])), 'warning');
        } else {
            flashAlert(escapeHtml($summary_msg));
        }
    } else {
        flashAlert("Weekly Technician Summary settings updated");
    }

    redirect();

}
