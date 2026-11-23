<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['reset_tbr_templates'])) {
    validateCSRFToken();
    enforceAdminPermission();

    $tpls = tbrGetDefaultEmailTemplates();
    $conf_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_confirmation_subject']);
    $conf_body = mysqli_real_escape_string($mysqli, $tpls['tpl_confirmation_body']);
    $rem_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_reminder_subject']);
    $rem_body = mysqli_real_escape_string($mysqli, $tpls['tpl_reminder_body']);
    $rep_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_report_subject']);
    $rep_body = mysqli_real_escape_string($mysqli, $tpls['tpl_report_body']);
    $int_sub = mysqli_real_escape_string($mysqli, $tpls['tpl_internal_reminder_subject']);
    $int_body = mysqli_real_escape_string($mysqli, $tpls['tpl_internal_reminder_body']);

    mysqli_query($mysqli, "
        UPDATE tbr_settings SET
            tpl_confirmation_subject = '$conf_sub',
            tpl_confirmation_body = '$conf_body',
            tpl_reminder_subject = '$rem_sub',
            tpl_reminder_body = '$rem_body',
            tpl_report_subject = '$rep_sub',
            tpl_report_body = '$rep_body',
            tpl_internal_reminder_subject = '$int_sub',
            tpl_internal_reminder_body = '$int_body'
        WHERE setting_id = 1
    ");

    logAudit('Settings', 'Edit', 'Reset TBR email templates to default', 0, 1);
    flashAlert('TBR email templates reset to defaults.', 'success');
    redirect('settings_tbr.php');
}

if (isset($_POST['save_tbr_settings'])) {
    validateCSRFToken();
    enforceAdminPermission();

    $enable_conf = !empty($_POST['enable_confirmation_email']) ? 1 : 0;
    $enable_7d = !empty($_POST['enable_7d_reminder']) ? 1 : 0;
    $rem_1_days = max(1, intval($_POST['reminder_1_days'] ?? 7));
    $enable_1d = !empty($_POST['enable_1d_reminder']) ? 1 : 0;
    $rem_2_days = max(1, intval($_POST['reminder_2_days'] ?? 1));
    $enable_day_of = !empty($_POST['enable_day_of_reminder']) ? 1 : 0;
    $day_of_hrs = max(1, intval($_POST['day_of_hours_before'] ?? 3));
    $enable_internal = !empty($_POST['enable_internal_reminders']) ? 1 : 0;
    $enable_overdue = !empty($_POST['enable_overdue_reminders']) ? 1 : 0;
    $enable_action_due = !empty($_POST['enable_action_due_reminders']) ? 1 : 0;
    $action_due_days = max(1, intval($_POST['action_due_days_before'] ?? 3));

    $sc_conf = !empty($_POST['send_client_confirmation']) ? 1 : 0;
    $sc_7d = !empty($_POST['send_client_7d']) ? 1 : 0;
    $sc_1d = !empty($_POST['send_client_1d']) ? 1 : 0;
    $sc_day_of = !empty($_POST['send_client_day_of']) ? 1 : 0;

    $si_conf = !empty($_POST['send_internal_confirmation']) ? 1 : 0;
    $si_7d = !empty($_POST['send_internal_7d']) ? 1 : 0;
    $si_1d = !empty($_POST['send_internal_1d']) ? 1 : 0;
    $si_day_of = !empty($_POST['send_internal_day_of']) ? 1 : 0;

    $attach_ics = !empty($_POST['attach_ics_calendar']) ? 1 : 0;
    $default_dur = max(15, intval($_POST['default_duration_minutes'] ?? 60));
    $conf_label = escapeSql($_POST['default_confidentiality_label'] ?? 'CONFIDENTIAL');

    $tpl_conf_sub = escapeSql($_POST['tpl_confirmation_subject'] ?? '');
    $tpl_conf_body = mysqli_real_escape_string($mysqli, (string)($_POST['tpl_confirmation_body'] ?? ''));
    $tpl_rem_sub = escapeSql($_POST['tpl_reminder_subject'] ?? '');
    $tpl_rem_body = mysqli_real_escape_string($mysqli, (string)($_POST['tpl_reminder_body'] ?? ''));
    $tpl_rep_sub = escapeSql($_POST['tpl_report_subject'] ?? '');
    $tpl_rep_body = mysqli_real_escape_string($mysqli, (string)($_POST['tpl_report_body'] ?? ''));
    $tpl_int_sub = escapeSql($_POST['tpl_internal_reminder_subject'] ?? '');
    $tpl_int_body = mysqli_real_escape_string($mysqli, (string)($_POST['tpl_internal_reminder_body'] ?? ''));

    mysqli_query($mysqli, "
        UPDATE tbr_settings SET
            enable_confirmation_email = $enable_conf,
            enable_7d_reminder = $enable_7d,
            reminder_1_days = $rem_1_days,
            enable_1d_reminder = $enable_1d,
            reminder_2_days = $rem_2_days,
            enable_day_of_reminder = $enable_day_of,
            day_of_hours_before = $day_of_hrs,
            enable_internal_reminders = $enable_internal,
            enable_overdue_reminders = $enable_overdue,
            enable_action_due_reminders = $enable_action_due,
            action_due_days_before = $action_due_days,
            send_client_confirmation = $sc_conf,
            send_client_7d = $sc_7d,
            send_client_1d = $sc_1d,
            send_client_day_of = $sc_day_of,
            send_internal_confirmation = $si_conf,
            send_internal_7d = $si_7d,
            send_internal_1d = $si_1d,
            send_internal_day_of = $si_day_of,
            attach_ics_calendar = $attach_ics,
            default_duration_minutes = $default_dur,
            default_confidentiality_label = '$conf_label',
            tpl_confirmation_subject = '$tpl_conf_sub',
            tpl_confirmation_body = '$tpl_conf_body',
            tpl_reminder_subject = '$tpl_rem_sub',
            tpl_reminder_body = '$tpl_rem_body',
            tpl_report_subject = '$tpl_rep_sub',
            tpl_report_body = '$tpl_rep_body',
            tpl_internal_reminder_subject = '$tpl_int_sub',
            tpl_internal_reminder_body = '$tpl_int_body'
        WHERE setting_id = 1
    ");

    if (isset($_POST['perms']) && is_array($_POST['perms'])) {
        foreach ($_POST['perms'] as $role_id_raw => $caps) {
            $rid = intval($role_id_raw);
            if ($rid <= 0 || $rid === 3) {
                continue;
            }
            $cv = !empty($caps['can_view']) ? 1 : 0;
            $cs = !empty($caps['can_schedule']) ? 1 : 0;
            $ce = !empty($caps['can_edit']) ? 1 : 0;
            $cc = !empty($caps['can_complete']) ? 1 : 0;
            $cp = !empty($caps['can_generate_pdf']) ? 1 : 0;
            $cem = !empty($caps['can_email_report']) ? 1 : 0;
            $ci = !empty($caps['can_view_internal_notes']) ? 1 : 0;
            $cr = !empty($caps['can_reopen']) ? 1 : 0;
            $cm = !empty($caps['can_manage_settings']) ? 1 : 0;

            mysqli_query($mysqli, "
                REPLACE INTO tbr_role_permissions SET
                    role_id = $rid,
                    can_view = $cv,
                    can_schedule = $cs,
                    can_edit = $ce,
                    can_complete = $cc,
                    can_generate_pdf = $cp,
                    can_email_report = $cem,
                    can_view_internal_notes = $ci,
                    can_reopen = $cr,
                    can_manage_settings = $cm
            ");
        }
    }

    logAudit('Settings', 'Edit', 'Updated TBR settings and role permissions', 0, 1);
    flashAlert('TBR settings and permissions saved.', 'success');
    redirect('settings_tbr.php');
}
